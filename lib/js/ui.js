import { CSS, CREDITS_HOMEPAGE, CREDITS_TEXT } from './constants.js';

export const UI = {
    /**
     * Builds the HTML for the credits section of the captcha.
     * @returns {string} The HTML string for the credits section.
     */
    buildCredits: (options) => {
        if (options.credits === 'show' || options.credits === 'hide') {
            return '<span' + (options.credits === 'hide' ? ' style="display:none;"' : '') + '>' +
                '<a href="' + CREDITS_HOMEPAGE + '" target="_blank" rel="follow" title="' + CREDITS_TEXT + '">JdzCaptcha</a>' +
                ' &copy;' +
                '</span>';
        }
        return '';
    },

    /**
     * Builds the HTML for the initial state of the captcha.
     * @param {Object} options The configuration options for the captcha.
     * @return {string} The HTML string for the captcha holder.
     */
    buildCaptchaInitialHolder: (options) => {
        return '<div class="' + CSS.box + '">' +
            '<div class="' + CSS.boxH + '">' +
            '<div class="' + CSS.circle + '"></div>' +
            '<div class="' + CSS.info + '">' + UI.buildCredits(options) + '</div>' +
            '<div class="' + CSS.title + '">' + options.messages.initialization.verify + '</div>' +
            '</div>' +
            '</div>';
    },

    /**
     * Builds the HTML for the challenge state of the captcha.
     * @param {Object} options The configuration options for the captcha.
     * @param {string} captchaId The captcha identifier.
     * @return {string} The HTML string for the captcha holder.
     */
    buildCaptchaHolder: (options, captchaId) => {
        return '<div class="' + CSS.box + '">' +
            '<div class="' + CSS.boxH + '"><span>' + options.messages.header + '</span></div>' +
            '<div class="' + CSS.boxB + '"><div class="' + CSS.icons + '"></div></div>' +
            '<div class="' + CSS.boxF + '">' + UI.buildCredits(options) + '</div>' +
            '<div class="' + CSS.fields + '">' +
            '<input type="hidden" name="jdzc[' + options.fields.selection + ']" required />' +
            '<input type="hidden" name="jdzc[' + options.fields.id + ']" value="' + captchaId + '" required />' +
            '<input type="hidden" name="jdzc[' + options.fields.honeypot + ']" required />' +
            '</div>' +
            '</div>';
    },

    /**
     * Builds the HTML for the checkmark icon.
     * @return {string} The HTML string for the checkmark icon.
     */
    buildCheckmark: () => {
        return '<svg viewBox="0 0 98.5 98.5" xml:space="preserve" xmlns="http://www.w3.org/2000/svg">' +
            '<path class="checkmark" d="M81.7 17.8C73.5 9.3 62 4 49.2 4 24.3 4 4 24.3 4 49.2s20.3 45.2 45.2 45.2 45.2-20.3 45.2-45.2c0-8.6-2.4-16.6-6.5-23.4L45.6 68.2 24.7 47.3" fill="none" stroke-miterlimit="10" stroke-width="8" />' +
            '</svg>';
    },

    buildValidSelectionMessage: (options) => {
        return '<div class="' + CSS.title + '">' + options.messages.correct + '</div>' +
            '<div class="' + CSS.checkmark + '">' + UI.buildCheckmark() + '</div>';
    },

    buildInvalidSelectionMessage: (options) => {
        return '<div class="' + CSS.title + '">' + options.messages.incorrect.title + '</div>' +
            '<div class="' + CSS.subtitle + '">' + options.messages.incorrect.subtitle + '</div>';
    },

    buildErrorMessage: (topMessage, bottomMessage) => {
        return '<div class="' + CSS.title + '"> ' + topMessage + '</div>' +
            '<div class="' + CSS.subtitle + '">' + bottomMessage + '</div>';
    },

    /**
     * Adds a loading spinner to the captcha holder element.
     * @param {HTMLElement} $el The captcha icon holder element.
     */
    addLoadingSpinner: ($el) => {
        $el.classList.add(CSS.opacity);
        if (!$el.querySelector(`.${CSS.loader} `)) {
            $el.insertAdjacentHTML('beforeend', `<div class="${CSS.loader}"></div>`);
        }
    },

    /**
     * Removes the loading spinner from the captcha holder element.
     * @param {HTMLElement} $el The captcha icon holder element.
     */
    removeLoadingSpinner: ($el) => {
        $el.classList.remove(CSS.opacity);

        if ($el.querySelector(`.${CSS.loader} `)) {
            $el.querySelector(`.${CSS.loader} `).remove();
        }
    },

    /**
     * Removes the loading spinner when the background image of the given DOM element is fully loaded.
     * @param {HTMLElement} $el The DOM element with the background image.
     * @param {Function} removeSpinnerCallback The callback to remove the spinner.
     */
    removeLoadingSpinnerOnImageLoad: ($el, removeSpinnerCallback) => {
        const imageUrl = $el.style.backgroundImage.match(/\((.*?)\)/)?.[1]?.replace(/(['"])/g, '');
        if (!imageUrl) return;

        const imgObject = new Image();

        // Remove the spinner once the image is loaded.
        imgObject.onload = () => removeSpinnerCallback();

        // Set the image source to trigger the `onload` event.
        imgObject.src = imageUrl;
    },
};