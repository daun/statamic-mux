<template>
    <span class="flex items-center gap-1">
        <ui-badge v-for="policy in policies" :key="policy" pill size="sm">
            <template v-if="policy === 'public'">
                {{ __('Public') }}
            </template>
            <template v-else-if="policy === 'signed'">
                {{ __('Signed') }}
            </template>
        </ui-badge>
    </span>
</template>

<script>
export default {
    props: {
        value: { type: String, default: null },
    },

    computed: {
        policies() {
            return (this.value ?? '')
                .split(',')
                .map((policy) => policy.trim())
                .filter(Boolean)
                .map((policy) => policy.toLowerCase());
        },
    },
};
</script>
