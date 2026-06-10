import {
    Badge,
    Button,
    ButtonGroup,
    ConfirmationModal,
    Dropdown,
    DropdownItem,
    DropdownLabel,
    DropdownMenu,
    DropdownSeparator,
    Header,
    Icon,
    Listing,
} from '@statamic/cms/ui';

export default {
    components: { Badge, Button, ButtonGroup, ConfirmationModal, Dropdown, DropdownItem, DropdownLabel, DropdownMenu, DropdownSeparator, Header, Icon, Listing },

    props: {
        refreshEndpoint: { type: String, default: null },
        commandEndpoint: { type: String, default: null },
        dashboardUrl: { type: String, default: null },
    },

    data() {
        return {
            runningCommand: null,
        };
    },

    methods: {
        async runCommand(command) {
            if (this.runningCommand) return;

            if (command === 'prune' && !window.confirm(__('Prune orphaned videos from Mux? This queues deletion jobs for remote videos with no local asset.'))) {
                return;
            }

            if (!this.commandEndpoint) return;

            this.runningCommand = command;
            try {
                const response = await this.$axios.post(this.commandEndpoint, { command });
                Statamic.$toast.success(response.data.message || __('Mux command queued. Refresh later to see updates.'));
            } catch (e) {
                console.error(e);
                Statamic.$toast.error(e.response?.data?.message || __('Failed to queue Mux command'));
            } finally {
                this.runningCommand = null;
            }
        },

        hasMuxActions(row) {
            return Boolean(row?.mux_id);
        },

        primaryPlaybackId(row) {
            if (row?.playback_id) return row.playback_id;

            const playbackIds = Array.isArray(row?.playback_ids) ? row.playback_ids : [];
            return playbackIds.find((id) => id.policy === 'public')?.id || playbackIds[0]?.id || null;
        },

        playerUrl(row) {
            const playbackId = this.primaryPlaybackId(row);

            return playbackId
                ? `https://player.mux.com/${playbackId}`
                : null;
        },

        embedCode(row) {
            const url = this.playerUrl(row);

            return url
                ? `<iframe src="${url}" style="width: 100%; border: none; aspect-ratio: 16/9;" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;" allowfullscreen ></iframe>`
                : null;
        },

        async copyToClipboard(value) {
            if (value) {
                Statamic.$callbacks.call('copyToClipboard', value);
            }
        },

        copyAssetId(row) {
            return this.copyToClipboard(row.mux_id);
        },

        copyPlaybackId(row) {
            return this.copyToClipboard(this.primaryPlaybackId(row));
        },

        copyPlaybackUrl(row) {
            return this.copyToClipboard(this.playerUrl(row));
        },

        copyEmbedCode(row) {
            return this.copyToClipboard(this.embedCode(row));
        },

        statusColor(status) {
            return {
                ready: 'green',
                preparing: 'amber',
                waiting: 'gray',
                stale: 'red',
                errored: 'red',
            }[status] || 'gray';
        },

        statusLabel(status) {
            return {
                ready: __('Ready'),
                preparing: __('Preparing'),
                waiting: __('Waiting'),
                stale: __('Stale'),
                errored: __('Errored'),
            }[status] || status;
        },

        stateColor(state) {
            return {
                mirrored: 'green',
                orphaned: 'amber',
                duplicated: 'red',
            }[state] || 'gray';
        },

        formatDuration(seconds) {
            if (!seconds) return '—';
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = Math.floor(seconds % 60);
            if (h > 0) return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
            return `${m}:${String(s).padStart(2, '0')}`;
        },

        formatDate(iso) {
            if (!iso) return '—';
            return new Date(iso).toLocaleDateString(undefined, {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            });
        },
    },
};
