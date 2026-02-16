<div class="language-switcher">
    <select name="locale" id="language-select" onchange="switchLanguage(this.value)" class="form-select" style="display: inline-block; width: auto;">
        <option value="en" {{ app()->getLocale() == 'en' ? 'selected' : '' }}>English</option>
        <option value="fr" {{ app()->getLocale() == 'fr' ? 'selected' : '' }}>Français</option>
        <option value="pt" {{ app()->getLocale() == 'pt' ? 'selected' : '' }}>Português</option>
    </select>
</div>

<script>
function switchLanguage(locale) {
    window.location.href = "{{ url('/language') }}/" + locale;
}
</script>

