/**
 * @file
 * Message template overrides.
 */

((Drupal) => {
  /**
   * Overrides message theme function.
   *
   * @param {object} message
   *   The message object.
   * @param {string} message.text
   *   The message text.
   * @param {object} options
   *   The message context.
   * @param {string} options.type
   *   The message type.
   * @param {string} options.id
   *   ID of the message, for reference.
   *
   * @return {HTMLElement}
   *   A DOM Node.
   */
  Drupal.theme.message = function (message) {
    var messageWrapper = message.querySelector('[data-drupal-selector="messages"]');
    var type = message.getAttribute('data-drupal-message-type');
    var id = message.getAttribute('data-drupal-message-id');
    
    // Check if we're in a toast container or alert container
    var existingToastContainer = document.querySelector('.toast-container[data-drupal-messages]');
    var existingAlertContainer = document.querySelector('.alert-wrapper[data-drupal-messages]');
    
    // Get message text content
    var messageText = '';
    if (messageWrapper) {
      messageText = messageWrapper.textContent || messageWrapper.innerText;
    }
    
    // Create toast if toast container exists
    if (existingToastContainer) {
      return createToast(type, messageText, id);
    }
    
    // Otherwise create alert (default)
    return createAlert(type, messageText, id);
  };

  function createAlert(type, messageText, id) {
    var alertClass = 'alert-info';
    var icon = '';
    
    switch (type) {
      case 'status':
        alertClass = 'alert-success';
        icon = '<svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Success:"><use xlink:href="#check-circle-fill"/></svg>';
        break;
      case 'warning':
        alertClass = 'alert-warning';
        icon = '<svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:"><use xlink:href="#exclamation-triangle-fill"/></svg>';
        break;
      case 'error':
        alertClass = 'alert-danger';
        icon = '<svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Danger:"><use xlink:href="#exclamation-triangle-fill"/></svg>';
        break;
      default:
        alertClass = 'alert-info';
        icon = '<svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Info:"><use xlink:href="#info-fill"/></svg>';
    }
    
    var alertElement = document.createElement('div');
    alertElement.className = 'alert ' + alertClass + ' alert-dismissible fade show d-flex align-items-center';
    alertElement.setAttribute('role', 'alert');
    if (id) {
      alertElement.setAttribute('data-drupal-message-id', id);
    }
    if (type) {
      alertElement.setAttribute('data-drupal-message-type', type);
    }
    
    alertElement.innerHTML = 
      icon +
      '<div>' + messageText + '</div>' +
      '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    
    return alertElement;
  }

  function createToast(type, messageText, id) {
    var icon = '';
    var heading = '';
    var autohide = 'true';
    var role = 'status';
    
    switch (type) {
      case 'status':
        icon = '<svg class="bi flex-shrink-0 me-2" width="20" height="20" role="img" aria-label="Success:"><use xlink:href="#check-circle-fill"/></svg>';
        heading = 'Status message';
        autohide = 'true';
        role = 'status';
        break;
      case 'warning':
        icon = '<svg class="bi flex-shrink-0 me-2" width="20" height="20" role="img" aria-label="Warning:"><use xlink:href="#exclamation-triangle-fill"/></svg>';
        heading = 'Warning message';
        autohide = 'false';
        role = 'alert';
        break;
      case 'error':
        icon = '<svg class="bi flex-shrink-0 me-2" width="20" height="20" role="img" aria-label="Error:"><use xlink:href="#exclamation-triangle-fill"/></svg>';
        heading = 'Error message';
        autohide = 'false';
        role = 'alert';
        break;
      default:
        icon = '<svg class="bi flex-shrink-0 me-2" width="20" height="20" role="img" aria-label="Info:"><use xlink:href="#info-fill"/></svg>';
        heading = 'Informative message';
        autohide = 'true';
        role = 'status';
    }
    
    var toastElement = document.createElement('div');
    toastElement.className = 'toast fade';
    toastElement.setAttribute('role', role);
    toastElement.setAttribute('aria-live', 'assertive');
    toastElement.setAttribute('aria-atomic', 'true');
    toastElement.setAttribute('data-bs-autohide', autohide);
    if (id) {
      toastElement.setAttribute('data-drupal-message-id', id);
    }
    if (type) {
      toastElement.setAttribute('data-drupal-message-type', type);
    }
    
    toastElement.innerHTML = 
      '<div class="toast-header">' +
        icon +
        '<strong class="me-auto">' + heading + '</strong>' +
        '<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>' +
      '</div>' +
      '<div class="toast-body">' +
        messageText +
      '</div>';
    
    // Initialize the toast with Bootstrap and show it
    setTimeout(function() {
      if (window.bootstrap && window.bootstrap.Toast) {
        var bsToast = new window.bootstrap.Toast(toastElement);
        bsToast.show();
      }
    }, 100);
    
    return toastElement;
  }
})(Drupal);
