<template>
    <ButtonGroup v-if="can('trigger mux sync')">
        <Dropdown align="end">
            <template #trigger>
                <Button
                    icon="mux::reload"
                    :text="__('Sync')"
                    :loading="running"
                    :disabled="running !== null"
                />
            </template>
            <DropdownMenu>
                <DropdownItem icon="mux::cloud-transfer" @click="run('mirror')">
                    <span class="flex items-baseline gap-2">
                        <span class="font-medium">{{ __('Mirror') }}</span>
                        <span class="text-sm text-gray-400 dark:text-gray-500">{{ __('Upload and prune') }}</span>
                    </span>
                </DropdownItem>
                <DropdownSeparator />
                <DropdownItem icon="mux::cloud-upload" @click="run('upload')">
                    <span class="flex items-baseline gap-2">
                        <span class="font-medium">{{ __('Upload') }}</span>
                        <span class="text-sm text-gray-400 dark:text-gray-500">{{ __('Upload new videos to Mux') }}</span>
                    </span>
                </DropdownItem>
                <DropdownItem icon="mux::cloud-spark" @click="run('prune')">
                    <span class="flex items-baseline gap-2">
                        <span class="font-medium">{{ __('Prune') }}</span>
                        <span class="text-sm text-gray-400 dark:text-gray-500">{{ __('Delete orphaned Mux videos') }}</span>
                    </span>
                </DropdownItem>
            </DropdownMenu>
        </Dropdown>
    </ButtonGroup>
</template>

<script>
import {
    Button,
    ButtonGroup,
    Dropdown,
    DropdownItem,
    DropdownLabel,
    DropdownMenu,
    DropdownSeparator,
} from '@statamic/cms/ui';

export default {
    components: { Button, ButtonGroup, Dropdown, DropdownItem, DropdownLabel, DropdownMenu, DropdownSeparator },

    emits: ['completed'],

    props: {
        endpoint: { type: String, default: null },
    },

    data() {
        return {
            running: null,
        };
    },

    methods: {
        async run(command) {
            if (this.running) return;
            if (!this.endpoint) return;

            this.running = command;
            try {
                const response = await this.$axios.post(this.endpoint, { command });
                Statamic.$toast.success(response.data.message || __('Sync command queued. Refresh later to see updates.'));
                this.$emit('completed', { command, response: response.data });
            } catch (e) {
                console.error(e);
                Statamic.$toast.error(e.response?.data?.message || __('Failed to queue sync command'));
            } finally {
                this.running = null;
            }
        },
    },
};
</script>
