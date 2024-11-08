/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2024 Teclib' and contributors.
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

export class Component
{
    /**
     *
     * @param {string} component_name
     * @param {{}} app
     */
    constructor(component_name, app, element) {
        this.component_name = component_name;
        this.app = app;
        this.autoBind = {};
        this.host_element = $(element);

        this.host_element.on('hook:mounted', () => {
            this.#registerListeners();
        });
    }

    static _getProxy(component_name, app, element) {
        const c = new Component(component_name, app, element);
        // Proxy handler for action shortcuts
        const proxy_handler = {
            get: function(target, prop) {
                // if the prop exists in the target object, return it
                if (prop in target) {
                    const internal_prop = c[prop];
                    if (typeof internal_prop === 'function') {
                        return internal_prop.bind(c);
                    }
                    return internal_prop;
                }
                // return the doAction method with the prop as the action
                return function() {
                    return c.#doAction(prop, ...arguments);
                };
            }
        };
        return new Proxy(c, proxy_handler);
    }

    _getComponentUrl() {
        return `${CFG_GLPI.root_doc}/ajax/Component/${this.component_name}`;
    }

    #getActionData(action, data = {}, method = 'post') {
        const combined_data = {
            ...(this.autoBind?.global?.global || {}),
            ...(this.autoBind?.global?.[method] || {}),
            ...(this.autoBind?.[action]?.global || {}),
            ...(this.autoBind?.[action]?.[method] || {}),
            ...data,
        };
        // Use actual values for any ref props
        Object.entries(combined_data).forEach(([key, value]) => {
            if (typeof value.__v_isRef !== 'undefined') {
                combined_data[key] = value.value;
            }
        });
        return combined_data;
    }

    get(action, data = {}) {
        const url = `${this._getComponentUrl()}?action=${action}`;
        return $.get(url, data);
    }

    post(action, data = {}) {
        const url = `${this._getComponentUrl()}?action=${action}`;
        return $.post(url, data);
    }

    loadHtml(element, action, data = {}) {
        const request_data = this.#getActionData(action, data, 'post');
        return new Promise((resolve, reject) => {
            $(element).load(`${this._getComponentUrl()}?action=${action}`, request_data, (response, status, xhr) => {
                if (status === 'error') {
                    reject(xhr);
                } else {
                    resolve(response);
                }
            });
        });
    }

    #doAction(action, data = {}) {
        const method = action.startsWith('get') ? 'get' : 'post';
        // remove 'get' or 'post' from the action name
        action = action.replace(/^(get|post)/, '');
        return this[method](action, this.#getActionData(action, data, method));
    }

    #registerListeners() {
        const app_container = $(this.app._container);

        app_container.on('submit', 'form[data-vue-form]', (event) => {
            // Block default form submission and submit instead using AJAX to the same form URL
            event.preventDefault();
            const form = $(event.target);
            const form_key = form.attr('data-vue-form');
            const method = form.attr('method') || 'POST';
            const action = form.attr('action') !== '' ? form.attr('action') : `${this._getComponentUrl()}?action=${form_key}`;
            // get form data as object where keys are input names and values are input values
            const form_data = form.serializeArray().reduce((obj, item) => {
                obj[item.name] = item.value;
                return obj;
            }, {});
            // Add submitter button name/value to form data
            const submit_btn = $(event.originalEvent.submitter);
            if (submit_btn.length > 0) {
                form_data[submit_btn.attr('name')] = submit_btn.val();
            }

            $.ajax({
                url: action,
                method: method,
                data: form_data,
                success: (response) => {
                    form.trigger('glpi:submit:success', response);
                    window.displayAjaxMessageAfterRedirect();
                },
                error: (xhr) => {
                    form.trigger('glpi:submit:error', xhr);
                    window.displayAjaxMessageAfterRedirect();
                }
            });
            return false;
        });
    }
}
