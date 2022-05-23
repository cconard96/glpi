/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2022 Teclib' and contributors.
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

/** @global {GLPI} GLPI */

import GLPIModulePlugin from "../GLPIModulePlugin.js";
import "./ResultsView.js";
import "./Table.js";

export default class Search extends GLPIModulePlugin {

    initialize() {
        /** @type {GenericView} */
        this.fluid_search_view = null;
    }

    /**
     * Sets the provided view as the current fluid search view.
     *
     * This will wait for the DOM to be ready, so it may not be initialized immediately.
     * @param {ResultsView} view
     * @returns {Promise}
     */
    initFluidSearch(view) {
        if (document.readyState === 'complete') {
            this.fluid_search_view = view.getView();
            return Promise.resolve();
        }
        $(document).on('ready', () => {
            return new Promise((resolve) => {
                this.fluid_search_view = view.getView();
                resolve();
            });
        });
    }

    isFluidSearchInitialized() {
        return this.fluid_search_view !== null;
    }

    /**
     *
     * @param {0|1} state
     */
    toggleFoldSearch(state) {
        if (this.isFluidSearchInitialized()) {
            this.fluid_search_view.toggleFoldSearch(state);
        } else {
            // Fallback in case there is a search view not using the fluid search
            $('.search-form-container .search-form').toggle(state);
        }
    }

    getLegacyGlobals() {
        return {
            'toggle_fold_search': {
                module_property: 'toggleFoldSearch',
                bind_target: this
            },
        };
    }
}

// Auto-register plugin
window.GLPI.registerModule('search', new Search());
