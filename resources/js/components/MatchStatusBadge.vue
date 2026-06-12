<template>
    <Badge v-if="value" pill :color="color" class="text-2xs" v-tooltip="tooltip">
        {{ label }}
    </Badge>
</template>

<script>
import { Badge } from '@statamic/cms/ui';

export default {
    components: { Badge },

    props: {
        value: { type: String, default: null },
    },

    computed: {
        color() {
            return {
                mirrored: 'green',
                proxy: 'gray',
                orphaned: 'amber',
                duplicated: 'red',
            }[this.value] || 'gray';
        },

        label() {
            return {
                mirrored: __('Mirrored'),
                proxy: __('Placeholder'),
                orphaned: __('Orphaned'),
                duplicated: __('Duplicated'),
            }[this.value] || this.value;
        },

        tooltip() {
            return {
                mirrored: __('Linked to a local asset'),
                proxy: __('Represented by a local placeholder clip'),
                orphaned: __('Not linked to a local asset'),
                duplicated: __('Referenced by multiple local assets'),
            }[this.value] || null;
        },
    },
};
</script>
