// Confirmation dialogs: <form data-confirm="Question?"> asks before submitting.
// A submit button's own data-confirm takes precedence over the form's.
// <form id="x" data-require-selected> needs at least one ticked checkbox that belongs to it (form="x");
// "{count}" in the question becomes the number of ticked checkboxes.
document.addEventListener('submit', (event) => {
    const form = event.target;
    let message = event.submitter?.dataset.confirm ?? form.dataset.confirm;

    if (form.dataset.requireSelected !== undefined) {
        const count = document.querySelectorAll(`input[form="${form.id}"]:checked`).length;

        if (count === 0) {
            event.preventDefault();
            alert('Select at least one card first.');
            return;
        }

        message = message?.replace('{count}', count);
    }

    if (message && !confirm(message)) {
        event.preventDefault();
    }
});

// <input type="checkbox" data-select-all="x"> ticks every checkbox that belongs to form "x".
document.addEventListener('change', (event) => {
    const formId = event.target.dataset?.selectAll;

    if (formId) {
        document.querySelectorAll(`input[form="${formId}"]`).forEach((box) => {
            box.checked = event.target.checked;
        });
    }
});
