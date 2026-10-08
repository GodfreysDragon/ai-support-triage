<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!--
        "ST" monogram (Support Triage): the top of the S runs into the T's
        crossbar. Drawn with strokes in currentColor, so it takes the text
        colour it's placed in; fill="none" sits on the paths because callers
        add fill-current to the svg. public/favicon.svg uses the same paths.
    -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        stroke="currentColor"
        stroke-width="5.5"
        stroke-linecap="round"
        stroke-linejoin="round"
        :class="className"
        v-bind="$attrs"
    >
        <path
            fill="none"
            d="M42 9H15.5C10.5 9 6.5 11.6 6.5 15.7c0 4 3.2 5.7 7 7.2 4.1 1.6 7.5 3.4 7.5 8.3 0 4.5-3.4 7.3-7.8 7.3-3.4 0-6.1-1.5-7.6-4.1"
        />
        <path fill="none" d="M32 9v29.5" />
    </svg>
</template>
