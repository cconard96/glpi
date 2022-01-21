/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2022 Teclib' and contributors.
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

export default class Marketplace {

   constructor() {
      this.ajax_url = CFG_GLPI.root_doc+"/ajax/marketplace.php";
      this.current_page = 1;
      this.ajax_done = false;
      this.registerListeners();
   }

   registerListeners() {
      // plugin actions (install, enable, etc)
      $(document).on('click', '.marketplace .modify_plugin', (e) => {
         const button     = $(e.currentTarget);
         const buttons    = button.closest('.buttons');
         const li         = button.closest('li.plugin');
         const icon       = button.children('i');
         const installed  = button.closest('.marketplace').hasClass('installed');
         const action     = button.data('action');
         const plugin_key = li.data('key');

         icon
            .removeClass()
            .addClass('fas fa-spinner fa-spin');

         if (action === 'download_plugin'
            || action === 'update_plugin') {
            this.followDownloadProgress(button);
         }

         this.ajax_done = false;
         $.post(this.ajax_url, {
            'action': action,
            'key': plugin_key
         }).done((html) => {
            this.ajax_done = true;

            if (html.indexOf("cleaned") !== -1 && installed) {
               li.remove();
            } else {
               html = html.replace('cleaned', '');
               buttons.html(html);
               displayAjaxMessageAfterRedirect();
               this.addTooltips();
            }
         });
      });

      // sort control
      $(document).on('select2:select', '.marketplace .sort-control', () => {
         this.filterPluginList();
      });

      // pagination
      $(document).on('click', '.marketplace .pagination li', (e) => {
         const li   = $(e.currentTarget);
         const page = li.data('page');

         if (li.hasClass('nav-disabled')
            || li.hasClass('current')
            || isNaN(page)) {
            return;
         }

         this.refreshPlugins(page);
      });

      // filter by tag
      $(document).on('click', '.marketplace .plugins-tags .tag', (e) => {
         $(".marketplace:visible .plugins-tags .tag").removeClass('active');
         $(e.currentTarget).addClass('active');
         this.filterPluginList();
      });

      // filter plugin list when something typed in search input
      let chrono;
      $(document).on('input', '.marketplace .filter-list', () => {
         clearTimeout(chrono);
         chrono = setTimeout(() => {
            this.filterPluginList();
         }, 500);
      });

      // force refresh of plugin list
      $(document).on('click', '.marketplace .refresh-plugin-list', () => {
         this.refreshPlugins(this.current_page, true);
      });
   }

   filterPluginList(page, force) {
      page  = page || 1;
      force = force || false;

      const marketplace  = $('.marketplace:visible');
      const pagination   = marketplace.find('ul.pagination');
      const plugins_list = marketplace.find('ul.plugins');
      const dom_tag      = marketplace.find('.plugins-tags .tag.active');
      const tag_key      = dom_tag.length ? dom_tag.data('tag') : "";
      const filter_str   = marketplace.find('.filter-list').val();
      let sort         = 'sort-alpha-desc';

      if (marketplace.find(".sort-control").length > 0) {
         sort = marketplace.find(".sort-control").select2('data')[0].element.value;
      }

      plugins_list
         .append("<div class='loading-plugins'><i class='fas fa-spinner fa-pulse'></i></div>");
      pagination.find('li.current').removeClass('current');

      const jqxhr = $.get(this.ajax_url, {
         'action': 'refresh_plugin_list',
         'tab':    marketplace.data('tab'),
         'tag':    tag_key,
         'filter': filter_str,
         'force':  force ? 1 : 0,
         'page':   page,
         'sort':   sort,
      }).done((html) => {
         plugins_list.html(html);

         const nb_plugins = jqxhr.getResponseHeader('X-GLPI-Marketplace-Total');
         $.get(this.ajax_url, {
            'action': 'getPagination',
            'page':  page,
            'total': nb_plugins,
         }).done(function(html) {
            pagination.html(html);
         });
      });

      return jqxhr;
   }

   refreshPlugins(page, force) {
      force = force || false;
      const icon = $('.marketplace:visible .refresh-plugin-list');

      icon
         .removeClass('fa-sync-alt')
         .addClass('fa-spinner fa-spin');

      $.when(this.filterPluginList(page, force)).then(() => {
         icon
            .removeClass('fa-spinner fa-spin')
            .addClass('fa-sync-alt');
         this.current_page = page;

         this.addTooltips();
      });
   }

   addTooltips() {
      $(".qtip").remove();
      $(".marketplace:visible").find("[data-action][title], .add_tooltip").qtip({
         position: {
            viewport: $(window),
            my: "center left",
            at: "center right",
            adjust: {
               x: 2,
               method: "flip"
            }
         },
         style: {
            classes: 'qtip-dark'
         },
         show: {
            solo: true, // hide all other tooltips
         },
         hide: {
            event: 'click mouseleave'
         }
      });
   }

   followDownloadProgress(button) {
      const buttons    = button.closest('.buttons');
      const li         = button.closest('li.plugin');
      const plugin_key = li.data('key');

      const progress = $('<progress max="100" value="0"></progress>');
      buttons.html(progress);

      // we call a non-blocking loop function to send ajax request with a small delay
      function loop () {
         setTimeout(() => {
            $.get(this.ajax_url, {
               'action': 'get_dl_progress',
               'key': plugin_key
            }).done((progress_value) => {
               progress.attr('value', progress_value);
               if (progress_value < 100) {
                  loop();
               } else if (!this.ajax_done) {
                  // set an animated icon when decompressing
                  buttons.html('<i class="fas fa-cog fa-spin"></i>');

                  // display messages from backend
                  displayAjaxMessageAfterRedirect();
               }
            });
         }, 300);
      }

      loop();
   }
}

window.Marketplace = Marketplace;