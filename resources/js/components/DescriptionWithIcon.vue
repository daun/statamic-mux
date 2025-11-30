<template>
    <Description>
        <div class="flex items-center">
            <Icon :name="icon" class="size-4 mr-2" />
            <slot></slot>
        </div>
    </Description>
</template>

<script>
import { Description, Icon } from '@statamic/cms/ui';

export default {
    components: { Description, Icon },
    props: {
        icon: {
            type: String,
            default: 'info-square'
        }
    }
};
</script>
