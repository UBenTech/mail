document.addEventListener('DOMContentLoaded', function () {
    // Modal handling
    var composeModal = new bootstrap.Modal(document.getElementById('composeModal'), {});
    var newCampaignBtn = document.getElementById('newCampaignBtn');

    if (newCampaignBtn) {
        newCampaignBtn.addEventListener('click', function () {
            composeModal.show();
        });
    }

    // Schedule options toggle
    var scheduleSwitch = document.getElementById('scheduleSwitch');
    var scheduleOptions = document.getElementById('scheduleOptions');

    if (scheduleSwitch && scheduleOptions) {
        scheduleSwitch.addEventListener('change', function () {
            if (this.checked) {
                scheduleOptions.style.display = 'block';
            } else {
                scheduleOptions.style.display = 'none';
            }
        });
    }
});
