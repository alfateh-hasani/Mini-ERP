/* Match browser constraint messages to the application's selected language. */
(() => {
  'use strict';
  const arabic = document.documentElement.lang === 'ar';
  const message = field => {
    const valid = field.validity;
    if (valid.valueMissing) return arabic ? 'هذا الحقل مطلوب.' : 'This field is required.';
    if (valid.rangeUnderflow) return arabic ? `يجب أن تكون القيمة أكبر من أو تساوي ${field.min}.` : `Value must be greater than or equal to ${field.min}.`;
    if (valid.rangeOverflow) return arabic ? `يجب أن تكون القيمة أقل من أو تساوي ${field.max}.` : `Value must be less than or equal to ${field.max}.`;
    if (valid.tooShort) return arabic ? `أدخل ${field.minLength} أحرف على الأقل.` : `Enter at least ${field.minLength} characters.`;
    if (valid.tooLong) return arabic ? `أدخل ${field.maxLength} أحرف كحد أقصى.` : `Enter no more than ${field.maxLength} characters.`;
    if (valid.typeMismatch && field.type === 'email') return arabic ? 'أدخل بريداً إلكترونياً صحيحاً.' : 'Enter a valid email address.';
    if (valid.stepMismatch) return arabic ? 'أدخل قيمة تتوافق مع الخطوة المحددة لهذا الحقل.' : 'Enter a value matching this field’s allowed step.';
    return arabic ? 'أدخل قيمة صحيحة لهذا الحقل.' : 'Enter a valid value for this field.';
  };
  document.addEventListener('invalid', event => {
    const field = event.target;
    if (typeof field.setCustomValidity !== 'function') return;
    if (field.validity.customError) return;
    field.setCustomValidity('');
    field.setCustomValidity(message(field));
  }, true);
  ['input', 'change'].forEach(type => document.addEventListener(type, event => {
    event.target.setCustomValidity?.('');
  }, true));
})();
