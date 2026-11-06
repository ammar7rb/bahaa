(() => {
    const normalize = value => {
        let phone = value.trim().replace(/[٠-٩۰-۹]/g, digit => {
            const code = digit.charCodeAt(0);
            return String(code >= 0x06f0 ? code - 0x06f0 : code - 0x0660);
        }).replace(/[\s()\-\u200e\u200f]/g, '');
        if (phone.startsWith('+20')) phone = '0' + phone.slice(3).replace(/^0/, '');
        else if (phone.startsWith('0020')) phone = '0' + phone.slice(4).replace(/^0/, '');
        else if (phone.startsWith('20')) phone = '0' + phone.slice(2).replace(/^0/, '');
        return phone;
    };
    const selector = 'input[type=tel], input[name=phone], input[name=phone_number], input[name=mobile_number], input[name=receiver_phone], input[name=contact_person_phone]';
    const validate = input => {
        input.setAttribute('data-egypt-phone', '');
        const phone = normalize(input.value);
        input.setCustomValidity(!phone || /^01[0125][0-9]{8}$/.test(phone) ? '' : 'أدخل رقم موبايل مصري من 11 رقمًا يبدأ بـ 010 أو 011 أو 012 أو 015');
    };
    document.addEventListener('input', event => { if (event.target.matches(selector)) validate(event.target); }, true);
    document.addEventListener('blur', event => {
        if (event.target.matches(selector)) {
            event.target.value = normalize(event.target.value);
            validate(event.target);
        }
    }, true);
    document.addEventListener('submit', event => {
        event.target.querySelectorAll(selector).forEach(input => {
            input.value = normalize(input.value);
            validate(input);
        });
    }, true);
    document.querySelectorAll(selector).forEach(validate);
})();
