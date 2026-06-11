import { Head } from '@statamic/cms/inertia';
import {
    Badge,
    Button,
    ButtonGroup,
    Dropdown,
    DropdownItem,
    DropdownLabel,
    DropdownMenu,
    DropdownSeparator,
    Header,
    Icon,
    Listing,
} from '@statamic/cms/ui';

import SyncButton from '../components/SyncButton.vue';

export default {
    components: { Badge, Button, ButtonGroup, Dropdown, DropdownItem, DropdownLabel, DropdownMenu, DropdownSeparator, Head, Header, Icon, Listing, SyncButton },

    props: {
        refreshEndpoint: { type: String, default: null },
        commandEndpoint: { type: String, default: null },
        actionUrl: { type: String, default: null },
        dashboardUrl: { type: String, default: null },
    },

    methods: {
        hasMuxActions(row) {
            return Boolean(row?.mux_id);
        },

        primaryPlaybackId(row) {
            if (row?.processing_status === 'errored') return null;

            if (row?.playback_id) return row.playback_id;

            const playbackIds = Array.isArray(row?.playback_ids) ? row.playback_ids : [];
            return playbackIds.find((id) => id.policy === 'public')?.id || playbackIds[0]?.id || null;
        },

        playerUrl(row) {
            return row?.player_url || null;
        },

        streamUrl(row) {
            return row?.stream_url || null;
        },

        thumbnailUrl(row) {
            return row?.thumbnail_url || null;
        },

        embedCode(row) {
            return row?.embed_code || null;
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

        copyPlayerUrl(row) {
            return this.copyToClipboard(this.playerUrl(row));
        },

        copyEmbedCode(row) {
            return this.copyToClipboard(this.embedCode(row));
        },

        copyThumbnailUrl(row) {
            return this.copyToClipboard(this.thumbnailUrl(row));
        },

        // Mux processing pipeline state (remote-derived).
        processingStatusColor(status) {
            return {
                ready: 'green',
                preparing: 'blue',
                errored: 'red',
            }[status] || 'gray';
        },

        processingStatusLabel(status) {
            return {
                ready: __('Ready'),
                preparing: __('Preparing'),
                errored: __('Errored'),
            }[status] || status;
        },

        // Local tab: has this asset been uploaded to Mux?
        mirrorStatusColor(status) {
            return {
                uploaded: 'green',
                not_uploaded: 'amber',
            }[status] || 'gray';
        },

        mirrorStatusLabel(status) {
            return {
                uploaded: __('Uploaded'),
                not_uploaded: __('Not uploaded'),
            }[status] || status;
        },

        // Remote tab: how does this Mux asset map to local assets?
        matchStatusColor(status) {
            return {
                mirrored: 'green',
                proxy: 'gray',
                orphaned: 'amber',
                duplicated: 'red',
            }[status] || 'gray';
        },

    },
};
