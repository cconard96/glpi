<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2026 Teclib' and contributors.
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

use Glpi\Asset\Asset;
use Safe\DateTime;

use function Safe\strtotime;

/**
 * Store printer metrics
 */
class PrinterLog extends CommonDBChild
{
    public static $itemtype = 'itemtype';
    public static $items_id = 'items_id';
    public $dohistory       = false;

    public static function getTypeName($nb = 0)
    {
        return __('Page counters');
    }

    /**
     * Get metrics
     *
     * @param array|Printer|Asset $printers Printer instance
     * @param array         $user_filters User filters
     * @param string        $interval     Date interval string (e.g. 'P1Y' for 1 year)
     * @param DateTime|null $start_date   Start date for the metrics range
     * @param DateTime      $end_date     End date for the metrics range
     * @param string        $format       Format for the metrics data ('dynamic', 'daily', 'weekly', 'monthly', 'yearly')
     *
     * @return array An array of printer metrics data
     */
    final public static function getMetrics(
        array|Printer|Asset $printers,
        array $user_filters = [],
        string $interval = 'P1Y',
        ?\DateTime $start_date = null,
        \DateTime $end_date = new DateTime(),
        string $format = 'dynamic'
    ): array {
        global $DB;

        if ($printers && !is_array($printers)) {
            $printers = [$printers];
        }

        if (!$start_date) {
            $start_date = new DateTime(Session::getCurrentTime());
            $start_date->sub(new DateInterval($interval));
        }

        $filters = [
            ['date' => ['>=', $start_date->format('Y-m-d')]],
            ['date' => ['<=', $end_date->format('Y-m-d')]],
        ];
        $filters = array_merge($filters, $user_filters);

        $series = [];
        if (count($printers) > 1) {
            foreach ($printers as $printer) {
                $series += self::getMetrics(
                    $printer,
                    $user_filters,
                    $interval,
                    $start_date,
                    $end_date,
                    $format
                );
            }
        } else {
            $printer = $printers[0];

            $iterator = $DB->request([
                'FROM'   => self::getTable(),
                'WHERE'  => [
                    'itemtype' => $printer::class,
                    'items_id'  => $printer->fields['id'],
                ] + $filters,
                'ORDER'  => 'date ASC',
            ]);

            $series = iterator_to_array($iterator, false);

            if ($format == 'dynamic') {
                // Reduce the data to 25 points
                $count = count($series);
                $max_size = 25;
                if ($count > $max_size) {
                    // Keep one row every X entry using modulo
                    $modulo = round($count / $max_size);
                    $series = array_filter(
                        $series,
                        fn($k) => (($count - ($k + 1)) % $modulo) == 0,
                        ARRAY_FILTER_USE_KEY
                    );
                }
            } else {
                $formats = [
                    'daily' => 'Ymd', // Reduce the data to one point per day max
                    'weekly' => 'YoW', // Reduce the data to one point per week max
                    'monthly' => 'Ym', // Reduce the data to one point per month max
                    'yearly' => 'Y', // Reduce the data to one point per year max
                ];

                $series = array_filter(
                    $series,
                    function ($k) use ($series, $format, $formats) {
                        if (!isset($series[$k + 1])) {
                            return true;
                        }

                        $current_date = date($formats[$format], strtotime($series[$k]['date']));
                        $next_date = date($formats[$format], strtotime($series[$k + 1]['date']));
                        return $current_date !== $next_date;
                    },
                    ARRAY_FILTER_USE_KEY
                );
            }

            $series = [$printer->getID() => array_values($series)];
        }

        return $series;
    }

    /**
     * Get the label for a given column of glpi_printerlogs.
     * To be used when displaying the printed pages graph.
     *
     * @param string $key
     *
     * @return null|string null if the key didn't match any valid field
     */
    public static function getLabelFor($key): ?string
    {
        return match ($key) {
            'total_pages' => __('Total pages'),
            'bw_pages' => __('Black & White pages'),
            'color_pages' => __('Color pages'),
            'scanned' => __('Scans'),
            'rv_pages' => __('Recto/Verso pages'),
            'prints' => __('Prints'),
            'bw_prints' => __('Black & White prints'),
            'color_prints' => __('Color prints'),
            'copies' => __('Copies'),
            'bw_copies' => __('Black & White copies'),
            'color_copies' => __('Color copies'),
            'faxed' => __('Fax'),
            default => null,
        };
    }
}
