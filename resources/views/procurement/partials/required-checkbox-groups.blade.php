<script>
    document.querySelectorAll('[data-required-checkbox-group]').forEach(function (group) {
        var checkboxes = Array.from(group.querySelectorAll('input[type="checkbox"]'));
        if (!checkboxes.length) return;

        var synchronizeRequiredState = function () {
            var hasSelection = checkboxes.some(function (checkbox) { return checkbox.checked; });
            checkboxes[0].required = !hasSelection;
            checkboxes.slice(1).forEach(function (checkbox) { checkbox.required = false; });
        };

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', synchronizeRequiredState);
        });
        synchronizeRequiredState();
    });
</script>
