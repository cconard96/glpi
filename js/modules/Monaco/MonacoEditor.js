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

window.GLPI = window.GLPI || {};

export default class MonacoEditor {
    constructor(element_id, language, value = '', completions = []) {
        const el = document.getElementById(element_id);
        window.monaco.languages.registerCompletionItemProvider('twig', {
            provideCompletionItems: function (model, position) {
                const word = model.getWordUntilPosition(position);
                const range = {
                    startLineNumber: position.lineNumber,
                    endLineNumber: position.lineNumber,
                    startColumn: word.startColumn,
                    endColumn: word.endColumn,
                };
                return {
                    suggestions: ((range) => {
                        const suggestions = [];
                        // expand completions to monaco format
                        for (const completion of completions) {
                            suggestions.push({
                                label: completion.name,
                                kind: window.monaco.languages.CompletionItemKind[completion.type],
                                insertText: completion.name,
                                documentation: completion.name,
                                range: range,
                            });
                        }
                        return suggestions;
                    })(range)
                };
            }
        });
        const editor = window.monaco.editor.create(el, {
            value: value,
            language: language,
        });

        $("#webhook-payload-editor-container .editor_find").on('click', function() {
            editor.getAction('actions.find').run();
        });
        $("#webhook-payload-editor-container .editor_save").on('click', function() {
            $.ajax({
                url: CFG_GLPI['root_doc'] + '/ajax/webhook.php',
                type: 'POST',
                data: {
                    action: 'update_payload_template',
                    webhook_id: $("#webhook-payload-editor-container").data('webhook-id'),
                    payload_template: editor.getValue(),
                }
            }).done(function() {
                // eslint-disable-next-line no-undef
                glpi_toast_info(__('Saved'));
            });
        });
    }
}

window.GLPI.MonacoEditor = MonacoEditor;
