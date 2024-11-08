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

import { Component } from "./Component.js";

export class ComponentAutomount
{
    static start() {
        // Initial mounts
        $('*[data-vue-component]').each(function() {
            ComponentAutomount.mountVueComponent(this);
        });
        // Observe new elements with the attribute
        const observer = new MutationObserver((mutationsList) => {
            for (const mutation of mutationsList) {
                if (mutation.type === 'childList') {
                    for (const node of mutation.addedNodes) {
                        if (node.nodeType === 1) {
                            $(node).find('*[data-vue-component]').addBack('*[data-vue-component]').each(function() {
                                ComponentAutomount.mountVueComponent(this);
                            });
                        }
                    }
                }
            }
        });
        observer.observe(document.body, {childList: true, subtree: true});
    }

    static mountVueComponent(element) {
        const component = element.getAttribute('data-vue-component');
        const props = JSON.parse(element.getAttribute('data-vue-props') ?? '{}');
        const global_component_pattern = element.getAttribute('data-vue-global-component-pattern') ?? '^$';

        // Remove the attributes to avoid mounting it again and clean up the DOM
        element.removeAttribute('data-vue-component');
        element.removeAttribute('data-vue-props');
        element.removeAttribute('data-vue-global-component-pattern');
        if (typeof window.Vue.components[component] !== 'undefined') {
            const applet = window.Vue.createApp(window.Vue.components[component].component, props);
            // Register global components if needed
            const global_component_regexp = new RegExp(global_component_pattern, 'g');
            const global_components = window.Vue.getComponentsByName(global_component_regexp);
            for (const global_component_name in global_components) {
                const registered_name = global_component_name.replace(/\//g, '-').replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase();
                applet.component(registered_name, global_components[global_component_name].component);
            }

            applet.provide('component', Component._getProxy(component, applet, element));

            applet.onUnmount(() => {
                element.dispatchEvent(new CustomEvent('hook:unmounted'));
            });
            applet.mount(element);
            element.dispatchEvent(new CustomEvent('hook:mounted'));
        }
    }
}
