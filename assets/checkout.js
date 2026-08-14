(function () {
  'use strict';

  const config = window.WCDeliveryConfig || {};
  const selector = '#wc_delivery_date, input[id$="costasch-delivery-date"]';

  function initialiseDateFields() {
    if (typeof window.flatpickr !== 'function') {
      return;
    }

    document.querySelectorAll(selector).forEach((field) => {
      if (field.dataset.wcDeliveryReady === 'true') {
        return;
      }

      window.flatpickr(field, {
        dateFormat: 'Y-m-d',
        minDate: 'today',
        disable: [
          (date) => (config.disabledWeekdays || []).includes(date.getDay()),
          ...(config.blackoutDates || []),
        ],
      });
      field.dataset.wcDeliveryReady = 'true';
    });
  }

  document.addEventListener('DOMContentLoaded', initialiseDateFields);
  document.body.addEventListener('updated_checkout', initialiseDateFields);
  new MutationObserver(initialiseDateFields).observe(document.documentElement, { childList: true, subtree: true });
})();
