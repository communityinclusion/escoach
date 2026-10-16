(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.cardDetailPopup = {
    attach: function (context, settings) {
      // Find all card popup triggers once
      const cardTriggers = once(
        'card-detail-init',
        '.card-detail-popup',
        context,
      );

      cardTriggers.forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
          event.preventDefault();

          // Get modal info from data attributes
          const modalId = this.getAttribute('data-modal-id');
          const modalTitle =
            drupalSettings.talent?.card_popup?.[modalId]?.title || '';
          const modalBody =
            drupalSettings.talent?.card_popup?.[modalId]?.body || '';

          // Check if modal already exists
          let modalElement = document.getElementById(
            `cardModal-${modalId}`,
          );

          // If modal doesn't exist, create and inject it
          if (!modalElement) {
            const modalHTML = `
              <div class="modal fade" id="cardModal-${modalId}" tabindex="-1" aria-labelledby="cardModalLabel-${modalId}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="cardModalLabel-${modalId}">${modalTitle}</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      ${modalBody}
                    </div>
                  </div>
                </div>
              </div>
            `;

            // Append modal to body
            const div = document.createElement('div');
            div.innerHTML = modalHTML;
            document.body.appendChild(div.firstElementChild);

            // Get the newly created modal
            modalElement = document.getElementById(
              `cardModal-${modalId}`,
            );
          }

          // Open modal using Bootstrap API
          const modal = new bootstrap.Modal(modalElement);
          modal.show();
        });
      });
    },
  };
})(Drupal, drupalSettings);
