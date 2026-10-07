<script setup>
import { useI18n } from 'vue-i18n'
import { setLocale } from '@/i18n'

// Compact EN | SW toggle for pages that render outside DefaultLayout (login,
// change-password) and therefore have no AppHeader language dropdown.
const { t, locale } = useI18n()

// `onLight`: use on light backgrounds, where the default translucent-white pill
// (made for the dark login scene) would be invisible.
defineProps({ onLight: { type: Boolean, default: false } })
</script>

<template>
  <div class="lang-switch" :class="{ 'lang-switch--light': onLight }" role="group" :aria-label="t('common.language')">
    <button
      type="button"
      class="lang-switch__btn"
      :class="{ 'is-active': locale === 'sw' }"
      :aria-pressed="locale === 'sw'"
      @click="setLocale('sw')"
    >🇹🇿 SW</button>
    <button
      type="button"
      class="lang-switch__btn"
      :class="{ 'is-active': locale === 'en' }"
      :aria-pressed="locale === 'en'"
      @click="setLocale('en')"
    >🇬🇧 EN</button>
  </div>
</template>

<style scoped>
.lang-switch {
  display: inline-flex;
  padding: 2px;
  border-radius: 999px;
  background: rgba(255, 255, 255, .18);
  border: 1px solid rgba(255, 255, 255, .35);
  backdrop-filter: blur(6px);
}
.lang-switch--light {
  background: #f1f3f5;
  border-color: #ced4da;
  color: #212529;
}
.lang-switch__btn {
  border: 0;
  background: transparent;
  color: inherit;
  font-size: .8rem;
  font-weight: 600;
  line-height: 1;
  padding: .4rem .75rem;
  border-radius: 999px;
  cursor: pointer;
  min-height: 32px;
}
.lang-switch__btn.is-active {
  background: #007f3e;
  color: #fff;
}
.lang-switch__btn:focus-visible {
  outline: 2px solid #fcd116;
  outline-offset: 1px;
}
</style>
