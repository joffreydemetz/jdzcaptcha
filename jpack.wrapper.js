/*! JdzCaptcha v@VERSION | @DATE | [@BUNDLE] */
(function (global) {
    "use strict";

    if (typeof global !== "object" || !global || !global.document) {
        throw new Error("JdzCaptcha requires a window with a document");
    }

    if (typeof global.JdzCaptcha !== "undefined") {
        throw new Error("JdzCaptcha is already defined");
    }

    // @CODE

    global.JdzCaptcha = JdzCaptcha;

})(typeof window !== "undefined" ? window : this);