/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2021 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

// Must be a var instead of let or you may get a Cannot access before initialization error
var GLPI = GLPI || {};
GLPI.Search = GLPI.Search || {};

GLPI.Search.SearchTable = class {

    constructor(element_id) {
        this.element = $('#'+element_id);

        if (this.element) {
            this.element.on('click', 'th[data-searchopt-id]', (e) => {
                const search_opt = $(e.target).data('searchopt-id');
                const sort_order = $(e.target).data('sort-order');
                const query_params = new URLSearchParams(window.location.search);

                let orders = [];
                const sort_param = query_params.get('sort');
                if (sort_param !== null && sort_param.length > 0) {
                    sort_param.split(',').map((s) => {
                        const p = s.split('_');
                        orders.push({
                           'searchopt_id': p[0],
                           'order': p[1]
                        });
                    });
                }

                let existing_sort = false;
                const new_order = sort_order === 'ASC' ? 'DESC' : (sort_order === 'DESC' ? 'nosort' : 'ASC');
                for (let i = 0; i < orders.length; i++) {
                    if (orders[i]['searchopt_id'] == search_opt) {
                        orders[i]['order'] = new_order;
                        existing_sort = true;
                        break;
                    }
                }
                if (!existing_sort) {
                    orders.push({
                       'searchopt_id': search_opt,
                       'order': new_order
                    });
                }

                query_params.set('sort', orders.filter((o) => {
                    return o['searchopt_id'] !== '' && o['order'] !== 'nosort';
                }).map((o) => {
                    return o['searchopt_id']+'_'+o['order'];
                }).join(','));
                query_params.delete('reset');

                window.location.search = query_params.toString();
            });
        }
    }

    getItemtype() {
        return this.element.data('search-itemtype');
    }
};