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

/**
 *  Report class
 *
 * @since version 0.84
 *
 * @phpstan-type ReportData array{title: string, report_id: string, report_type: string, params: array, data: array}
 **/
class Report extends CommonGLPI
{
    /** @var bool */
    protected static $notable = false;
    public static $rightname         = 'reports';

    public static function getTypeName($nb = 0)
    {
        return _n('Report', 'Reports', $nb);
    }

    public static function getReports(): array
    {
        global $CFG_GLPI, $PLUGIN_HOOKS;

        $root_doc = $CFG_GLPI['root_doc'];

        // Report generation
        // Default Report included
        $report_list = [];
        $report_list["default"]["name"] = __('Default report');
        $report_list["default"]["file"] = $root_doc . "/front/report.default.php";

        if (Contract::canView()) {
            $report_list["Contrats"]["name"] = __('By contract');
            $report_list["Contrats"]["file"] = $root_doc . "/front/report.contract.php";
        }
        if (Infocom::canView()) {
            $report_list["Par_annee"]["name"] = __('By year');
            $report_list["Par_annee"]["file"] = $root_doc . "/front/report.year.php";
            $report_list["Infocoms"]["name"]  = __('Hardware financial and administrative information');
            $report_list["Infocoms"]["file"]  = $root_doc . "/front/report.infocom.php";
            $report_list["Infocoms2"]["name"] = __('Other financial and administrative information (licenses, cartridges, consumables)');
            $report_list["Infocoms2"]["file"] = $root_doc . "/front/report.infocom.conso.php";
        }
        if (Session::haveRight("networking", READ)) {
            // Network socket report
            $report_list["Rapport prises reseau"]["name"] = __('Network report');
            $report_list["Rapport prises reseau"]["file"] = $root_doc . "/front/report.networking.php";
        }
        if (Session::haveRight("reservation", READ)) {
            $report_list["reservation"]["name"] = __('Loan');
            $report_list["reservation"]["file"] = $root_doc . "/front/report.reservation.php";
        }
        //TODO This should probably check all state_types
        if (
            Computer::canView()
            || Monitor::canView()
            || Session::haveRight("networking", READ)
            || Peripheral::canView()
            || Printer::canView()
            || Phone::canView()
        ) {
            $report_list["state"]["name"] = __('Status');
            $report_list["state"]["file"] = $root_doc . "/front/report.state.php";
        }

        // Handle reports from plugins
        if (isset($PLUGIN_HOOKS["reports"]) && is_array($PLUGIN_HOOKS["reports"])) {
            foreach ($PLUGIN_HOOKS["reports"] as $plug => $pages) {
                if (!Plugin::isPluginActive($plug)) {
                    continue;
                }
                if (is_array($pages) && count($pages)) {
                    foreach ($pages as $page => $name) {
                        $report_list[Plugin::getInfo($plug, 'name')][$page] = [
                            'name' => $name,
                            'file' => "{$CFG_GLPI['root_doc']}/plugins/{$plug}/{$page}",
                            'plug' => $plug,
                        ];
                    }
                }
            }
        }

        return $report_list;
    }

    /**
     * @param string $interface
     *
     * @return array
     */
    public function getRights($interface = 'central')
    {
        return [ READ => __('Read')];
    }
}
