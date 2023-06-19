<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2023 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

use Glpi\Application\View\TemplateRenderer;
use Glpi\DBAL\QueryExpression;
use Glpi\DBAL\QueryFunction;

class QueuedWebhook extends CommonDBTM
{
    public static $rightname = 'config';

    public static function getTypeName($nb = 0)
    {
        return __('Webhook queue');
    }

    public static function canCreate()
    {
        // Everybody can create : human and cron
        return Session::getLoginUserID(false);
    }

    public static function canDelete()
    {
        return static::canUpdate();
    }

    public static function getForbiddenActionsForMenu()
    {
        return ['add'];
    }

    public function getForbiddenStandardMassiveAction()
    {

        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }

    public function getSpecificMassiveActions($checkitem = null, $is_deleted = false)
    {

        $isadmin = static::canUpdate();
        $actions = parent::getSpecificMassiveActions($checkitem);

        if ($isadmin && !$is_deleted) {
            $actions[__CLASS__ . MassiveAction::CLASS_ACTION_SEPARATOR . 'send'] = _x('button', 'Send');
        }

        return $actions;
    }

    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ) {
        /** @var QueuedWebhook $item */
        switch ($ma->getAction()) {
            case 'send':
                foreach ($ids as $id) {
                    if ($item->canEdit($id)) {
                        if ($item::sendById($id)) {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        }
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                    }
                }
                return;
        }
        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }

    public function prepareInputForAdd($input)
    {
        global $DB;

        if (!isset($input['create_time']) || empty($input['create_time'])) {
            $input['create_time'] = $_SESSION["glpi_currenttime"];
        }
        if (!isset($input['send_time']) || empty($input['send_time'])) {
            $toadd = 0;
            if (isset($input['entities_id'])) {
                $toadd = Entity::getUsedConfig('delay_send_emails', $input['entities_id']);
            }
            if ($toadd > 0) {
                $input['send_time'] = date(
                    "Y-m-d H:i:s",
                    strtotime($_SESSION["glpi_currenttime"])
                    + $toadd * MINUTE_TIMESTAMP
                );
            } else {
                $input['send_time'] = $_SESSION["glpi_currenttime"];
            }
        }
        $input['sent_try'] = 0;

        return $input;
    }

    /**
     * Send webhook in queue
     *
     * @param integer $ID Id
     *
     * @return boolean
     */
    public static function sendById(int $ID): bool
    {
        global $CFG_GLPI;

        $queued_webhook = new self();
        if (!$queued_webhook->getFromDB($ID)) {
            return false;
        }

        $options['timeout'] = 5;
        if (!empty($CFG_GLPI["proxy_name"])) {
            $proxy_creds = '';
            if (!empty($CFG_GLPI['proxy_user'])) {
                $proxy_creds = $CFG_GLPI['proxy_user'] . ":" . (new \GLPIKey())->decrypt($CFG_GLPI['proxy_passwd']) . "@";
            }
            $proxy_string     = "http://{$proxy_creds}" . $CFG_GLPI['proxy_name'] . ":" . $CFG_GLPI['proxy_port'];
            $options['proxy'] = $proxy_string;
        }

        $webhook = new Webhook();
        $webhook->getFromDB($queued_webhook->fields['webhooks_id']);

        $client = new \GuzzleHttp\Client($options);
        $headers = json_decode($queued_webhook->fields['headers'], true);
        try {
            $response = $client->request($webhook->fields['http_method'], $queued_webhook->fields['url'], [
                \GuzzleHttp\RequestOptions::HEADERS => $headers,
                \GuzzleHttp\RequestOptions::BODY => $queued_webhook->fields['body'],
            ]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            //TODO log the error
            $response = null;
        }
        if ($response !== null && $response->getStatusCode() === 200) {
            $queued_webhook->delete(['id' => $ID]);
            return true;
        }

        $queued_webhook->update([
            'id' => $ID,
            'sent_try' => $queued_webhook->fields['sent_try'] + 1,
            'sent_time' => $_SESSION["glpi_currenttime"],
        ]);
        return false;
    }

    public static function getIcon()
    {
        return "ti ti-notification";
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'                 => 'common',
            'name'               => __('Characteristics')
        ];

        $tab[] = [
            'id'                 => '2',
            'table'              => self::getTable(),
            'field'              => 'id',
            'name'               => __('ID'),
            'massiveaction'      => false,
            'datatype'           => 'itemlink'
        ];

        $tab[] = [
            'id'                 => '16',
            'table'              => self::getTable(),
            'field'              => 'create_time',
            'name'               => __('Creation date'),
            'datatype'           => 'datetime',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '3',
            'table'              => self::getTable(),
            'field'              => 'send_time',
            'name'               => __('Expected send date'),
            'datatype'           => 'datetime',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => self::getTable(),
            'field'              => 'sent_time',
            'name'               => __('Send date'),
            'datatype'           => 'datetime',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '7',
            'table'              => self::getTable(),
            'field'              => 'url',
            'name'               => __('URL'),
            'datatype'           => 'string',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '11',
            'table'              => self::getTable(),
            'field'              => 'headers',
            'name'               => __('Headers'),
            'datatype'           => 'specific',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '12',
            'table'              => self::getTable(),
            'field'              => 'body',
            'name'               => _n('Payload', 'Payloads', 1),
            'datatype'           => 'text',
            'massiveaction'      => false,
            'htmltext'           => true
        ];

        $tab[] = [
            'id'                 => '15',
            'table'              => self::getTable(),
            'field'              => 'sent_try',
            'name'               => __('Number of tries of sent'),
            'datatype'           => 'integer',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '20',
            'table'              => self::getTable(),
            'field'              => 'itemtype',
            'name'               => _n('Type', 'Types', 1),
            'datatype'           => 'itemtype',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '21',
            'table'              => self::getTable(),
            'field'              => 'items_id',
            'name'               => __('Associated item ID'),
            'massiveaction'      => false,
            'datatype'           => 'integer'
        ];

        $tab[] = [
            'id'                 => '22',
            'table'              => 'glpi_webhooks',
            'field'              => 'name',
            'name'               => Webhook::getTypeName(1),
            'massiveaction'      => false,
            'datatype'           => 'dropdown'
        ];

        $tab[] = [
            'id'                 => '80',
            'table'              => 'glpi_entities',
            'field'              => 'completename',
            'name'               => Entity::getTypeName(1),
            'massiveaction'      => false,
            'datatype'           => 'dropdown'
        ];

        return $tab;
    }

    public function showForm($ID, array $options = [])
    {
        $webhook = new Webhook();
        $webhook->getFromDB($this->fields['webhooks_id']);
        TemplateRenderer::getInstance()->display('pages/setup/webhook/queuedwebhook.html.twig', [
            'item' => $this,
            'item_obj' => new ($this->fields['itemtype'])(),
            'webhook' => $webhook,
            'headers' => json_decode($this->fields['headers'], true),
            'params' => [
                'canedit' => false,
                'candel' => $this->canDeleteItem()
            ]
        ]);
        return true;
    }

    /**
     * Get pending webhooks in queue
     *
     * @param string  $send_time   Maximum sent_time
     * @param integer $limit       Query limit clause
     * @param array   $extra_where Extra params to add to the where clause
     *
     * @return array Array of IDs of pending webhooks
     */
    public static function getPendings($send_time = null, $limit = 20, $extra_where = [])
    {
        global $DB;

        if ($send_time === null) {
            $send_time = date('Y-m-d H:i:s');
        }

        $pendings = [];
        $iterator = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => self::getTable(),
            'WHERE'  => [
                    'is_deleted'   => 0,
                    'send_time'    => ['<', $send_time],
                ] +  $extra_where,
            'ORDER'  => 'send_time ASC',
            'START'  => 0,
            'LIMIT'  => $limit
        ]);
        if ($iterator->numRows() > 0) {
            foreach ($iterator as $row) {
                $pendings[] = $row;
            }
        }

        return $pendings;
    }

    /**
     * Cron action on webhook queue: send webhooks in queue
     *
     * @param CronTask|null $task for log (default NULL)
     *
     * @return integer either 0 or 1
     **/
    public static function cronQueuedWebhook(CronTask $task = null)
    {
        $cron_status = 0;

        // Send webhooks at least 1 minute after adding in queue to be sure that process on it is finished
        $send_time = date("Y-m-d H:i:s", strtotime("+1 minutes"));

        $limit = $task !== null ? $task->fields['param'] : 50;

        $pendings = self::getPendings($send_time, $limit);

        foreach ($pendings as $data) {
            self::sendById($data['id']);
        }

        return $cron_status;
    }


    /**
     * Cron action on webhook queue: clean webhook queue
     *
     * @param CronTask|null $task for log (default NULL)
     *
     * @return integer either 0 or 1
     **/
    public static function cronQueuedWebhookClean(CronTask $task = null)
    {
        global $DB;

        $vol = 0;

        $expiration = $task !== null ? $task->fields['param'] : 30;

        if ($expiration > 0) {
            $secs      = $expiration * DAY_TIMESTAMP;
            $send_time = date("U") - $secs;
            $DB->delete(
                self::getTable(),
                [
                    'is_deleted'   => 1,
                    new QueryExpression(QueryFunction::unixTimestamp('send_time') . ' < ' . $DB::quoteValue($send_time))
                ]
            );
            $vol = $DB->affectedRows();
        }

        $task->setVolume($vol);
        return ($vol > 0 ? 1 : 0);
    }
}
