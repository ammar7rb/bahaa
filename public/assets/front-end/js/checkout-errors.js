(function (root) {
    'use strict';
    function texts(value) {
        if (typeof value === 'string') return value.trim() ? [value.trim()] : [];
        if (Array.isArray(value)) return value.flatMap(texts);
        if (value && typeof value === 'object') {
            if ('message' in value) return texts(value.message);
            return Object.entries(value).filter(([key]) => key !== 'code').flatMap(([, item]) => texts(item));
        }
        return [];
    }
    function message(error, fallback = 'تعذر إتمام الخطوة. راجع البيانات وحاول مرة أخرى.') {
        const body = error?.responseJSON || error;
        const messages = texts(body?.errors);
        if (messages.length) return [...new Set(messages)].join(' ');
        if (error?.status === 419) return 'انتهت الجلسة. حدّث الصفحة ثم حاول مرة أخرى.';
        if (error?.status === 401) return 'سجّل الدخول مرة أخرى لاستكمال طلبك.';
        if (error?.status === 0) return 'تعذر الاتصال. تحقق من الإنترنت وحاول مرة أخرى.';
        return texts(body?.message)[0] || fallback;
    }
    root.CheckoutErrors = {message};
    if (typeof module !== 'undefined') module.exports = {message};
})(typeof window === 'undefined' ? globalThis : window);
