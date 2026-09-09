// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Quick translate sidebar controller.
 *
 * @module     tool_quicktranslate/main
 * @copyright  2024 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import {exception as displayException} from 'core/notification';
import Templates from 'core/templates';
import {debounce} from 'core/utils';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import {getString} from 'core/str';

const DEBOUNCE_TIMER = 500;

const Selectors = {
    menu: '#quicktranslate_menu',
    menuToggler: '#quicktranslate_menu_toggler',
    close: '#quicktranslate_close',
    search: '#quicktranslate_search',
    clearSearchBtn: '#quicktranslate_clear_search_btn',
    refreshBtn: '#quicktranslate_refresh_btn',
    item: '.quicktranslate-item',
    itemText: '.translate',
    translatesForm: '#translates_form',
    list: '#quicktranslate_strings_list',
    data: '#quicktranslate_data',
    status: '#quicktranslate_status',
    statusText: '#quicktranslate_status_text',
    dismiss: '[data-action="dismiss"]',
};

/**
 * Fetches all translations for a string identifier from the server.
 *
 * @param {string} reference String reference in format identifier/component.
 * @return {Promise<object>} Resolves with the translation data.
 */
const getTranslate = (reference) => fetchMany([{
    methodname: 'tool_quicktranslate_get_translated_string',
    args: {reference},
}])[0];

/**
 * Persists updated translations for a string identifier.
 *
 * @param {string} reference String reference in format identifier/component.
 * @param {Array<object>} translations List of {lang, string} objects.
 * @return {Promise<object>} Resolves with the save result.
 */
const setTranslate = (reference, translations) => fetchMany([{
    methodname: 'tool_quicktranslate_set_translated_string',
    args: {reference, translations},
}])[0];

/**
 * Shows or hides list items by whether they match the query. An empty query shows everything.
 *
 * @param {string} searchQuery Lowercase trimmed search string.
 */
const showSearchResult = (searchQuery) => {
    document.querySelectorAll(Selectors.item).forEach((element) => {
        const matches = element.textContent.toLowerCase().includes(searchQuery);
        element.closest('li').classList.toggle('d-none', !matches);
    });
};

/**
 * Builds the string rows, once, from the json the page shipped.
 *
 * A page in edit mode resolves hundreds of strings; rendering them up front made this panel most
 * of the page's DOM whether or not it was opened. The data has to travel with the page, because
 * the strings exist only while it renders - a later request would collect its own. Consuming the
 * data node is also the guard against rendering twice.
 *
 * @param {HTMLElement} list The list element to fill.
 * @param {HTMLElement} data The json script element holding the strings.
 * @return {Promise<void>}
 */
const renderList = (list, data) => {
    if (!data.isConnected) {
        return Promise.resolve();
    }

    const langstrings = JSON.parse(data.textContent);
    data.remove();

    return Templates.renderForPromise('tool_quicktranslate/sidebar_items', {langstrings})
        .then(({html, js}) => Templates.replaceNodeContents(list, html, js))
        .catch(displayException);
};

/**
 * Reports a saved translation in the panel's own status region.
 *
 * Not core/toast: it puts its region at z-index 1051, which a theme navbar can also claim, hiding
 * the message behind it. Here the markup is ours, next to the refresh this message asks for.
 *
 * @param {string} oldTranslate The text of the string that was changed.
 * @return {Promise<void>}
 */
const showSaved = (oldTranslate) => getString('stringwasupdated', 'tool_quicktranslate', oldTranslate)
    .then((message) => {
        document.querySelector(Selectors.statusText).textContent = message;
        document.querySelector(Selectors.status).classList.remove('d-none');

        return undefined;
    })
    .catch(displayException);

/**
 * Collects textarea values from the form and sends them to the server.
 *
 * @param {HTMLFormElement} form The translation form element.
 * @param {string} oldTranslate The text of the string being changed.
 * @return {Promise<void>}
 */
const saveTranslate = (form, oldTranslate) => {
    const translations = [...form.querySelectorAll('textarea')].map((el) => ({
        string: el.value.trim(),
        lang: el.dataset.lang.toLowerCase(),
    }));

    return setTranslate(form.dataset.id, translations)
        .then(() => showSaved(oldTranslate))
        .catch(displayException);
};

/**
 * Opens a save/cancel modal pre-populated with translation textareas for all languages.
 *
 * @param {object} templateContext Translation data from the server.
 * @param {string} oldTranslate The text of the string being changed.
 * @return {Promise<void>}
 */
const showModal = async(templateContext, oldTranslate) => {
    const modal = await ModalSaveCancel.create({
        large: true,
        title: getString('setnewtranslate', 'tool_quicktranslate'),
        body: Templates.render('tool_quicktranslate/translation_form', templateContext),
        show: true,
        removeOnClose: true,
    });

    modal.getRoot().on(ModalEvents.save, () => {
        const form = modal.getBody()[0].querySelector(Selectors.translatesForm);
        if (form) {
            saveTranslate(form, oldTranslate);
        }
    });
};

/**
 * Opens or closes the panel.
 *
 * Two controls change this state, so the class and aria-expanded are set in one place.
 *
 * @param {HTMLElement} menuToggler The floating toggle, whose aria-expanded describes the panel.
 * @param {boolean} shown
 */
const setShown = (menuToggler, shown) => {
    document.body.classList.toggle('quicktranslate-menu-shown', shown);
    menuToggler.setAttribute('aria-expanded', shown ? 'true' : 'false');
};

/**
 * Initialises the sidebar: attaches all event listeners.
 */
export const init = () => {
    const menu = document.querySelector(Selectors.menu);
    const menuToggler = document.querySelector(Selectors.menuToggler);
    const search = document.querySelector(Selectors.search);
    const clearSearchBtn = document.querySelector(Selectors.clearSearchBtn);
    const refreshBtn = document.querySelector(Selectors.refreshBtn);
    const status = document.querySelector(Selectors.status);
    const list = document.querySelector(Selectors.list);
    const data = document.querySelector(Selectors.data);
    const closeBtn = document.querySelector(Selectors.close);

    if (!menu || !menuToggler || !search || !clearSearchBtn || !refreshBtn || !status || !list || !data
            || !closeBtn) {
        return;
    }

    menu.addEventListener('click', (event) => {
        const element = event.target.closest(Selectors.item);
        if (!element) {
            return;
        }
        getTranslate(element.dataset.id)
            .then((result) => showModal(result, element.querySelector(Selectors.itemText).textContent.trim()))
            .catch(displayException);
    });

    clearSearchBtn.addEventListener('click', () => {
        search.value = '';
        showSearchResult('');
        clearSearchBtn.classList.add('d-none');
        search.focus();
    });

    refreshBtn.addEventListener('click', () => window.location.reload());

    // Bootstrap's data-bs-dismiss deletes the alert; the next save needs it back.
    status.querySelector(Selectors.dismiss).addEventListener('click', () => status.classList.add('d-none'));

    menuToggler.addEventListener('click', () => {
        setShown(menuToggler, true);
        renderList(list, data);
    });

    closeBtn.addEventListener('click', () => setShown(menuToggler, false));

    search.addEventListener('input', debounce(() => {
        const query = search.value.toLowerCase().trim();
        showSearchResult(query);
        clearSearchBtn.classList.toggle('d-none', query === '');
    }, DEBOUNCE_TIMER));
};
