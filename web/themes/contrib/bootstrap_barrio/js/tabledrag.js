/**
 * @file
 * tabledrag.js overrides and extensions.
 */

(($, Drupal) => {
  /**
   * Extends core's Tabledrag functionality.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the tabledrag weight toggler button.
   */
  Drupal.behaviors.tableHelpers = {
    attach(context, settings) {
      const initTableDrag = (table, base) => {
        if (table.length && Drupal.tableDrag[base]) {
          const tableClass = 'table';
          if (Drupal.tableDrag[base].mainTable) {
            Drupal.tableDrag[base].mainTable.addClass(tableClass);
          }

          Drupal.tableDrag[base].updateFields = () => {
            $('td.tabledrag-hide', Drupal.tableDrag[base].table[0]).each(
              function eachHideTableDrag() {
                this.classList.add('visually-hidden');
              }
            );
          };

          Drupal.tableDrag[base].updateFields();

          Drupal.theme.tableDragToggle().insertBefore(
            settings.tableDrag[base]
          );
        }
      };

      Object.keys(settings.tableDrag || {}).forEach((base) => {
        const tableElement = document.getElementById(base);
        if (tableElement) {
          tableElement.classList.remove('table-bordered');
          tableElement.classList.add('table-hover');
          initTableDrag($(once(
            'tabledrag', `#${base}`, context)
            ), base);
        }
      });
    },
  };

  /**
   * Theme functions.
   */
  $.extend(
    Drupal.theme,
    /** @lends Drupal.theme */ {
      /**
       * Constructs the table drag changed warning.
       *
       * @return {string}
       *   Markup for the warning.
       */
      tableDragChangedWarning() {
        // Check if we're in a toast container or alert container
        var existingToastContainer = document.querySelector('.toast-container[data-drupal-messages]');
        var existingAlertContainer = document.querySelector('.alert-wrapper[data-drupal-messages]');
        
        if (existingToastContainer) {
          // Create toast warning
          var toastElement = document.createElement('div');
          toastElement.className = 'toast fade show';
          toastElement.setAttribute('role', 'alert');
          toastElement.setAttribute('aria-live', 'assertive');
          toastElement.setAttribute('aria-atomic', 'true');
          toastElement.setAttribute('data-bs-autohide', 'false'); // Don't auto-hide warnings
          
          toastElement.innerHTML = 
            '<div class="toast-header">' +
              '<svg class="bi flex-shrink-0 me-2" width="20" height="20" role="img" aria-label="Warning:"><use xlink:href="#exclamation-triangle-fill"/></svg>' +
              '<strong class="me-auto">Warning message</strong>' +
              '<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>' +
            '</div>' +
            '<div class="toast-body">' +
              Drupal.theme('tableDragChangedMarker') + ' ' + Drupal.t('You have unsaved changes.') +
            '</div>';
          
          existingToastContainer.appendChild(toastElement);
          return '';
        }
        
        if (existingAlertContainer) {
          // Create alert warning
          var warningElement = document.createElement('div');
          warningElement.setAttribute('aria-label', 'Warning');
          warningElement.setAttribute('data-component-id', 'bootstrap_barrio:alert');
          warningElement.className = 'alert alert-dismissible d-flex align-items-center fade show alert-warning';
          warningElement.setAttribute('data-drupal-selector', 'messages');
          warningElement.setAttribute('role', 'alert');
          
          warningElement.innerHTML = `
            <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:"><use xlink:href="#exclamation-triangle-fill"/></svg>
            <div>
              <h2 class="alert-heading visually-hidden">Warning</h2>
              ${Drupal.theme('tableDragChangedMarker')} ${Drupal.t('You have unsaved changes.')}
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          `;
          
          existingAlertContainer.appendChild(warningElement);
          return '';
        }
        
        // Fallback if no wrapper exists - return alert format
        return `<div aria-label="Warning" data-component-id="bootstrap_barrio:alert" class="alert alert-dismissible d-flex align-items-center fade show alert-warning" data-drupal-selector="messages" role="alert">
          <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:"><use xlink:href="#exclamation-triangle-fill"/></svg>
          <div>
            <h2 class="alert-heading visually-hidden">Warning</h2>
            ${Drupal.theme('tableDragChangedMarker')} ${Drupal.t('You have unsaved changes.')}
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;
      },

      /**
       * The button for toggling table row weight visibility.
       *
       * @return {string}
       *   HTML markup for the weight toggle button and its container.
       */
      tableDragToggle: () =>
        `<div class="tabledrag-toggle-weight-wrapper" data-drupal-selector="tabledrag-toggle-weight-wrapper">
            <button type="button" class="link action-link tabledrag-toggle-weight btn btn-secondary btn-sm" data-drupal-selector="tabledrag-toggle-weight"></button>
        </div>`,
    }
  );
})(jQuery, Drupal);
