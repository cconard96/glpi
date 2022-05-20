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

import Clipboard from './Clipboard.js';

/**
 * The core GLPI module code.
 * This code should be loaded globally for all pages.
 */

// Save any previous value of GLPI.
if (window.GLPI !== undefined) {
    window._GLPI = window.GLPI;
}
window.GLPI = new class GLPI {
    constructor() {
        /** @type {Object.<string, GLPIModulePlugin>} */
        this.modules = {};

        /** @type {EventTarget} */
        this.event_target = new EventTarget();
    }

    initialize() {
        this.redefineAlert();
        this.redefineConfirm();

        this.registerCoreModules();
    }

    redefineAlert() {
        window.old_alert = window.alert;
        /**
         * @param {string} message
         * @param {string} caption
         */
        window.alert = (message, caption) => {
            // Don't apply methods on undefined objects... ;-) #3866
            if (typeof message == 'string') {
                message = message.replace('\\n', '<br>');
            }
            caption = caption || _n('Information', 'Information', 1);

            glpi_alert({
                title: caption,
                message: message,
            });
        };
    }

    redefineConfirm() {
        let confirmed = false;
        let lastClickedElement;

        // store last clicked element on dom
        $(document).click(function(event) {
            lastClickedElement = $(event.target);
        });

        // asynchronous confirm dialog with jquery ui
        const newConfirm = function(message, caption) {
            message = message.replace('\\n', '<br>');
            caption = caption || '';

            glpi_confirm({
                title: caption,
                message: message,
                confirm_callback: function() {
                    confirmed = true;

                    //trigger click on the same element (to return true value)
                    lastClickedElement.click();

                    // re-init confirmed (to permit usage of 'confirm' function again in the page)
                    // maybe timeout is not essential ...
                    setTimeout(function() {
                        confirmed = false;
                    }, 100);
                }
            });
        };

        window.nativeConfirm = window.confirm;

        // redefine native 'confirm' function
        window.confirm = function (message, caption) {
            // if watched var isn't true, we can display dialog
            if(!confirmed) {
                // call asynchronous dialog
                newConfirm(message, caption);
            }

            // return early
            return confirmed;
        };
    }

    registerCoreModules() {
        this.registerModule('clipboard', new Clipboard());
    }

    /**
     * Register a module.
     * @param {string} key The module key
     * @param {GLPIModulePlugin} module
     */
    registerModule(key, module) {
        this.modules[key] = module;
        this.bindLegacyGlobals(module);
        module.initialize();
        this.getEventTarget().dispatchEvent(new CustomEvent('module:registered', {
            detail: {
                module: module
            }
        }));
    }

    /**
     * Bind functions to "window" for legacy (non-module) support.
     * @param {GLPIModulePlugin} module
     */
    bindLegacyGlobals(module) {
        const globals = module.getLegacyGlobals();

        Object.entries(globals).forEach(([key, value]) => {
            if (window[key] !== undefined) {
                throw new Error(`Legacy global "${key}" already exists.`);
            }
            window[key] = module[value];
        });
    }

    /**
     * Check if a module is registered.
     *
     * @param {string} key The module key
     * @returns {boolean}
     */
    isModuleRegistered(key) {
        return this.modules[key] !== undefined;
    }

    /**
     * Get a module by the key it was registered with.
     *
     * @param {string} key
     * @returns {GLPIModulePlugin|undefined}
     */
    getModule(key) {
        return this.modules[key];
    }

    /**
     * Get the event target object.
     *
     * @returns {EventTarget}
     */
    getEventTarget() {
        return this.event_target;
    }
};
// Merge any classes/code that may have been loaded before the core GLPI module.
Object.assign(window.GLPI, window._GLPI);
delete window._GLPI;

window.GLPI.initialize();
