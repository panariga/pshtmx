document.addEventListener('DOMContentLoaded', function () {
  const fetchButton = document.getElementById('fetch-latest-htmx');
  const urlInput = document.querySelector('input[name="PSHTMX_SCRIPT_URL"]');
  const hashInput = document.querySelector('input[name="PSHTMX_SRI_HASH"]');

  if (!fetchButton || !urlInput || !hashInput) {
    console.error('PSHTMX: Could not find all required form elements.');
    return;
  }
  // Check if our AJAX URL was passed from PHP
  if (typeof pshtmx_ajax_url === 'undefined') {
    console.error('PSHTMX: AJAX URL is not defined.');
    fetchButton.disabled = true;
    fetchButton.innerText = 'Configuration Error!';
    return;
  }

  fetchButton.addEventListener('click', function () {
    const originalButtonText = this.innerHTML;
    this.innerHTML = '<i class="icon-spinner icon-spin"></i> Fetching...';
    this.disabled = true;

    // The unpkg.com '.meta' endpoint provides a JSON with resolved URL and integrity hash
    fetch(pshtmx_ajax_url)
      .then(response => {
        if (!response.ok) {
          // Try to get a more detailed error message from our controller
          return response.json().then(errorData => {
            throw new Error(errorData.message || 'Network response was not ok.');
          });
        }
        return response.json();
      })
      .then(data => {
        // *** THIS IS THE ONLY CHANGE ***
        // Match the new keys from our PHP controller
        if (data.resolved_url && data.sri_hash) {
          urlInput.value = data.resolved_url;
          hashInput.value = data.sri_hash;
          showPSAlert('Successfully fetched the latest HTMX version details!', 'success');
        } else {
          throw new Error('Invalid data received from proxy.');
        }
      })
      .catch(error => {
        console.error('PSHTMX: Failed to fetch HTMX info:', error);
        showPSAlert('Error: Could not fetch HTMX details. Check browser console for more info.', 'danger');
      })
      .finally(() => {
        // Restore button state
        this.innerHTML = originalButtonText;
        this.disabled = false;
      });
  });

  // Helper function to show a PrestaShop-style alert
  function showPSAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.style.marginTop = '15px';
    alertDiv.innerHTML = `<button type="button" class="close" data-dismiss="alert">×</button>${message}`;

    const form = document.querySelector('.defaultForm');
    if (form) {
      form.prepend(alertDiv);
    }
  }
});