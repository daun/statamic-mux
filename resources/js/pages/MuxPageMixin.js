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

import MatchStatusBadge from '../components/MatchStatusBadge.vue';
import MirrorStatusBadge from '../components/MirrorStatusBadge.vue';
import ProcessingStatusBadge from '../components/ProcessingStatusBadge.vue';
import SyncButton from '../components/SyncButton.vue';

export default {
    components: {
        Badge,
        Button,
        ButtonGroup,
        Dropdown,
        DropdownItem,
        DropdownLabel,
        DropdownMenu,
        DropdownSeparator,
        Head,
        Header,
        Icon,
        Listing,
        MatchStatusBadge,
        MirrorStatusBadge,
        ProcessingStatusBadge,
        SyncButton,
    },

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
            return row?.thumbnail_copy_url || row?.thumbnail_url || null;
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

    },
};
