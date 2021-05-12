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

import GenericView from './GenericView.js';

// Explicitly bind to window so Jest tests work properly
window.GLPI = window.GLPI || {};
window.GLPI.Search = window.GLPI.Search || {};

window.GLPI.Search.Table = class Table extends GenericView {

   constructor(result_view_element_id) {
      const element_id = $('#'+result_view_element_id).find('table.search-results').attr('id');
      super(element_id);
   }

   getElement() {
      return $('#'+this.element_id);
   }

   onColumnSortClick(target) {
      const target_column = $(target);
      const all_colums = this.getElement().find('thead th');
      const sort_order = target_column.data('sort-order');

      const new_order = sort_order === 'ASC' ? 'DESC' : (sort_order === 'DESC' ? 'nosort' : 'ASC');
      target_column.data('sort-order', new_order);

      let sort_num = target_column.data('sort-num');

      const recalulate_sort_nums = () => {
         let sort_nums = [];

         // Add sort nums to an array in order
         all_colums.each((i, c) => {
            const col = $(c);
            if (col.data('sort-num') !== undefined) {
               sort_nums[col.data('sort-num')] = col.data('searchopt-id');
            }
         });

         // Re-index array
         sort_nums = sort_nums.filter(v => v);

         // Convert to object and flip keys and values
         const sort_nums_obj = {};
         sort_nums.forEach((v, k) => {
            sort_nums_obj[v] = k;
         });

         // Clear sort-nums from all columns or change value
         all_colums.each((i, c) => {
            const col = $(c);
            col.data('sort-num', undefined);
            if (sort_nums_obj.hasOwnProperty(col.data('searchopt-id'))) {
               col.data('sort-num', sort_nums_obj[col.data('searchopt-id')]);
            }
         });
      };

      if (sort_num === undefined && new_order !== 'nosort') {
         // Recalculate sort-num on other columns, then set new sort-num
         recalulate_sort_nums();
         target_column.data('sort-num', all_colums.filter(function() {
            return $(this).data('sort-num') !== undefined;
         }).length);
      } else if (sort_num !== undefined && new_order === 'nosort') {
         // Remove sort-num and recalculate sort-num on other columns
         target_column.data('sort-num', undefined);
         recalulate_sort_nums();
      }

      this.refreshResults();
   }

   onLimitChange(target) {
      const new_limit = target.value;
      $(target).closest('form').find('select.search-limit-dropdown').each(function() {
         $(this).val(new_limit);
      });

      this.refreshResults();
   }

   onPageChange(target) {
      const page_link = $(target);
      page_link.closest('.pagination').find('.page-item').removeClass('active');
      page_link.parent().addClass('active');

      this.refreshResults();
   }

   refreshResults(search_overrides = {}) {
      this.showLoadingSpinner();
      const el = this.getElement();
      const form_el = el.closest('form');
      const ajax_container = el.closest('.ajax-container');
      let search_data = {};
      try {
         if (search_overrides['reset']) {
            search_data = {
               action: 'display_results',
               searchform_id: this.element_id,
               itemtype: this.getItemtype(),
               reset: 'reset'
            };
         } else {
            const sort_state = this.getSortState();
            const limit = $(form_el).find('select.search-limit-dropdown').first().val();
            const search_form_values = $(ajax_container).closest('.search-container').find('.search-form-container').serializeArray();
            let search_criteria = {};
            search_form_values.forEach((v) => {
               search_criteria[v['name']] = v['value'];
            });
            const start = $(ajax_container).find('.pagination .page-item.active .page-link').data('start');
            search_criteria['start'] = start || 0;

            search_data = Object.assign({
               action: 'display_results',
               searchform_id: this.element_id,
               itemtype: this.getItemtype(),
               sort: sort_state['sort'],
               order: sort_state['order'],
               glpilist_limit: limit,
            }, search_criteria, search_overrides);
         }

         $(ajax_container).load(CFG_GLPI.root_doc + '/ajax/search.php', search_data, () => {
            this.hideLoadingSpinner();
         });
      } catch {
         this.hideLoadingSpinner();
      }
   }

   registerListeners() {
      const ajax_container = this.getResultsView().getAJAXContainer();
      const search_container = ajax_container.closest('.search-container');

      $(ajax_container).on('click', 'table.search-results th[data-searchopt-id]', (e) => {
         e.stopPropagation();
         this.onColumnSortClick($(e.target).closest('th').get(0));
      });

      $(ajax_container).on('change', 'select.search-limit-dropdown', (e) => {
         this.onLimitChange(e.target);
      });

      $(ajax_container).on('click', '.pagination .page-link', (e) => {
         this.onPageChange(e.target);
      });

      $(search_container).on('click', '.search-form-container button[name="search"]', (e) => {
         e.preventDefault();
         this.onSearch();
      });
   }

   getItemtype() {
      return this.getResultsView().getElement().data('search-itemtype');
   }

   getSortState() {
      const columns = this.getElement().find('thead th[data-searchopt-id]:not([data-searchopt-id=""])[data-sort-order]:not([data-sort-order=""])');
      const sort_state = {
         sort: [],
         order: []
      };
      columns.each((i, c) => {
         const col = $(c);

         const order = col.data('sort-order');
         if (order !== 'nosort') {
            sort_state['sort'][col.data('sort-num') ?? 0] = col.data('searchopt-id');
            sort_state['order'][col.data('sort-num') ?? 0] = order;
         }
      });
      return sort_state;
   }
};
