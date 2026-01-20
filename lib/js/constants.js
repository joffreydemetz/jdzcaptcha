export const VERSION = '2.0.0';
export const CREDITS_TEXT = 'JdzCaptcha by Joffrey Demetz';
export const CREDITS_HOMEPAGE = 'https://jdzcaptcha.joffreydemetz.com';
export const APISERVER = 'https://api.jdzcaptcha.joffreydemetz.com/v2/';

export const CSS = {
    opacity: 'jdzc-opacity',
    error: 'jdzc-error',
    success: 'jdzc-success',
    init: 'jdzc-init',
    loader: 'jdzc-loader',
    selection: 'jdzc-box-selection',
    box: 'jdzc-box',
    boxH: 'jdzc-box-h',
    boxB: 'jdzc-box-b',
    boxF: 'jdzc-box-f',
    circle: 'jdzc-box-circle',
    info: 'jdzc-box-info',
    title: 'jdzc-box-title',
    icons: 'jdzc-box-icons',
    checkmark: 'jdzc-box-checkmark',
    subtitle: 'jdzc-box-subtitle',
    fields: 'jdzc-fields',
};

export const defaults = {
    path: APISERVER + '/captcha/request/',
    token: true,
    fontFamily: '',
    credits: 'show',
    theme: 'lc',
    variant: 'light',
    security: {
        clickDelay: 1500,
        hoverDetection: true,
        enableInitialMessage: true,
        initializeDelay: 500,
        selectionResetDelay: 3000,
        loadingAnimationDelay: 1000,
        invalidateTime: 1000 * 60 * 2,
    },
    fields: {
        selection: '_jdzc-hf-se',
        id: '_jdzc-hf-id',
        honeypot: '_jdzc-hf-hp',
        token: '_jdzc-token',
    },
    messages: {
        initialization: {
            loading: 'Loading challenge...',
            verify: 'Verify that you are human.',
        },
        header: 'Select the image displayed the <u>least</u> amount of times',
        correct: 'Verification complete.',
        incorrect: {
            title: 'Uh oh.',
            subtitle: 'You’ve selected the wrong image.',
        },
        timeout: {
            title: 'Please wait 60 sec.',
            subtitle: 'You made too many incorrect selections.',
        },
    },
};