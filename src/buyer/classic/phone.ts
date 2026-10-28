type PhoneWindow = Window & {
  kiriofBillingAddressConfig?: { i18n?: { invalidPhone?: string } };
  kiriofClassicPhoneValidation?: { dispose(): void };
  jQuery?: (node: Element) => {
    on(events: string, callback: () => boolean | void): unknown;
    off(events: string): unknown;
  };
};

export function validCheckoutPhone(value: string): boolean {
  if (value.length > 64) return false;
  const phone = value.replace(/^ +| +$/g, '');
  const digits = phone.replace(/[^0-9]/g, '');
  return (
    /^\+?(?:[0-9]|\([0-9]+\))(?:[0-9]|[ -](?=[0-9(])|\([0-9]+\))*(?![\s\S])/.test(phone) &&
    digits.length >= 8 &&
    digits.length <= 15
  );
}

export function startClassicPhoneValidation(root: PhoneWindow = window as PhoneWindow) {
  if (root.kiriofClassicPhoneValidation) return root.kiriofClassicPhoneValidation;
  const document = root.document;
  const form = document.querySelector<HTMLFormElement>('form.checkout, form.woocommerce-checkout');
  if (!form || document.querySelector('.wp-block-woocommerce-checkout, .wc-block-checkout')) return;
  const message =
    root.kiriofBillingAddressConfig?.i18n?.invalidPhone ||
    'Enter a valid phone number with 8–15 digits. Spaces, hyphens, parentheses and a leading + are allowed.';
  const touched = new WeakSet<HTMLInputElement>();
  const managed = new Map<HTMLInputElement, string | null>();
  let submitted = false;
  let disposed = false;
  const inputs = () => [
    ...form.querySelectorAll<HTMLInputElement>('[name="billing_phone"], [name="shipping_phone"]'),
  ];
  const inScope = () =>
    [
      ...form.querySelectorAll<HTMLInputElement>(
        'input.shipping_method:checked, input.shipping_method[type="hidden"], input[name^="shipping_method"]:checked',
      ),
    ].some((input) => /^kiriminaja-(official|instant)($|[_:])/.test(input.value));
  const active = (input: HTMLInputElement) =>
    !input.disabled &&
    (input.name === 'billing_phone' ||
      !!form.querySelector<HTMLInputElement>('[name="ship_to_different_address"]')?.checked);
  const update = (input: HTMLInputElement, show: boolean) => {
    const id = `${input.name}-kiriof-phone-error`;
    const row = input.closest('.form-row');
    const invalid =
      inScope() &&
      active(input) &&
      (input.name === 'billing_phone' || input.value !== '' || input.required) &&
      !validCheckoutPhone(input.value);
    const visible = invalid && show;
    let error = form.querySelector<HTMLElement>(`#${id}`);
    if (visible && !error) {
      error = document.createElement('span');
      error.id = id;
      error.className = 'kiriof-phone-error';
      error.setAttribute('aria-live', 'polite');
      input.insertAdjacentElement('afterend', error);
    }
    if (error) {
      error.textContent = visible ? message : '';
      error.hidden = !visible;
    }
    const descriptions = (input.getAttribute('aria-describedby') || '')
      .split(/\s+/)
      .filter((value) => value && value !== id);
    if (visible) descriptions.push(id);
    if (descriptions.length) input.setAttribute('aria-describedby', descriptions.join(' '));
    else input.removeAttribute('aria-describedby');
    if (visible) {
      if (!managed.has(input)) managed.set(input, input.getAttribute('aria-invalid'));
      input.setAttribute('aria-invalid', 'true');
      row?.classList.add('woocommerce-invalid', 'woocommerce-invalid-phone');
      row?.classList.remove('woocommerce-validated');
      if (!input.validity.customError || input.validationMessage === message)
        input.setCustomValidity(message);
    } else if (managed.has(input)) {
      const previous = managed.get(input);
      managed.delete(input);
      if (previous === null) input.removeAttribute('aria-invalid');
      else if (previous !== undefined) input.setAttribute('aria-invalid', previous);
      if (!input.validity.customError || input.validationMessage === message)
        row?.classList.remove('woocommerce-invalid', 'woocommerce-invalid-phone');
      if (input.validationMessage === message) input.setCustomValidity('');
    }
    return !invalid;
  };
  const refresh = () => {
    if (disposed) return;
    inputs().forEach((input) => update(input, submitted || touched.has(input)));
  };
  const afterWooValidation = () => root.queueMicrotask(refresh);
  const event = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (input.matches?.('[name="billing_phone"], [name="shipping_phone"]')) {
      touched.add(input);
      update(input, event.type !== 'input' || input.value !== '' || submitted);
    } else if (event.type === 'change') refresh();
  };
  const submit = () => {
    submitted = true;
    const invalid = inputs().filter((input) => !update(input, true));
    if (invalid.length) invalid[0].focus();
    return invalid.length === 0;
  };
  const nativeSubmit = (event: Event) => {
    if (!submit()) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  };
  form.addEventListener('input', event);
  form.addEventListener('focusout', event);
  form.addEventListener('change', event);
  form.addEventListener('submit', nativeSubmit, true);
  root.jQuery?.(form).on('checkout_place_order.kiriofPhone', submit);
  root.jQuery?.(document.body).on('updated_checkout.kiriofPhone', refresh);
  root.jQuery?.(document.body).on('validate.kiriofPhone', afterWooValidation);
  const bridge = {
    dispose() {
      disposed = true;
      form.removeEventListener('input', event);
      form.removeEventListener('focusout', event);
      form.removeEventListener('change', event);
      form.removeEventListener('submit', nativeSubmit, true);
      root.jQuery?.(form).off('.kiriofPhone');
      root.jQuery?.(document.body).off('.kiriofPhone');
      for (const [input, previous] of managed) {
        if (!input.validity.customError || input.validationMessage === message)
          input
            .closest('.form-row')
            ?.classList.remove('woocommerce-invalid', 'woocommerce-invalid-phone');
        if (input.validationMessage === message) input.setCustomValidity('');
        if (input.getAttribute('aria-invalid') === 'true') {
          if (previous === null) input.removeAttribute('aria-invalid');
          else input.setAttribute('aria-invalid', previous);
        }
      }
      for (const input of [...inputs(), ...managed.keys()]) {
        const descriptions = (input.getAttribute('aria-describedby') || '')
          .split(/\s+/)
          .filter((value) => value && value !== `${input.name}-kiriof-phone-error`);
        if (descriptions.length) input.setAttribute('aria-describedby', descriptions.join(' '));
        else input.removeAttribute('aria-describedby');
      }
      managed.clear();
      form.querySelectorAll('.kiriof-phone-error').forEach((error) => error.remove());
      delete root.kiriofClassicPhoneValidation;
    },
  };
  root.kiriofClassicPhoneValidation = bridge;
  return bridge;
}
