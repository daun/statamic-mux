export default {
    data() {
        return {
            listingPollingCallbackName: null,
            listingPollingVisibleRows: {},
            listingPollingRows: {},
            listingPollingTimer: null,
            listingPollingBusy: false,
        };
    },

    mounted() {
        if (this.listingPollingCallbackName) {
            Statamic.$callbacks.add(this.listingPollingCallbackName, this.startListingRowPolling);
        }
    },

    beforeUnmount() {
        this.stopListingRowPolling();

        if (this.listingPollingCallbackName) {
            Statamic.$callbacks.add(this.listingPollingCallbackName, () => {});
        }
    },

    methods: {
        listingPollingRequestCompleted({ items }) {
            this.listingPollingVisibleRows = items.reduce((rows, row) => {
                if (row.id) rows[row.id] = row;
                if (row.path) rows[row.path] = row;

                return rows;
            }, {});

            items.forEach((row) => this.setListingPollingRowUpdating(row, Boolean(this.listingPollingKeyForRow(row))));
        },

        startListingRowPolling(rows, mode = 'sync') {
            const keys = [...new Set((Array.isArray(rows) ? rows : [rows]).filter(Boolean))];

            keys.forEach((key) => {
                this.listingPollingRows[key] = this.listingPollingRows[key] || { mode, attempts: 0 };
                this.setListingPollingRowUpdating(key, true);
            });

            if (!keys.length) return;

            this.pollListingRows();

            if (!this.listingPollingTimer) {
                this.listingPollingTimer = setInterval(() => this.pollListingRows(), 3000);
            }
        },

        startListingRowPollingForVisibleRows(mode = 'sync') {
            this.startListingRowPolling(this.listingPollingVisibleRowKeys(), mode);
        },

        listingPollingVisibleRowKeys() {
            const keys = new Set();

            Object.values(this.listingPollingVisibleRows).forEach((row) => {
                const key = row?.id || row?.path;
                if (key) keys.add(key);
            });

            return [...keys];
        },

        stopListingRowPolling() {
            if (this.listingPollingTimer) {
                clearInterval(this.listingPollingTimer);
                this.listingPollingTimer = null;
            }

            Object.values(this.listingPollingVisibleRows)
                .forEach((row) => this.setListingPollingRowUpdating(row, false));

            this.listingPollingRows = {};
            this.listingPollingBusy = false;
        },

        async pollListingRows() {
            if (this.listingPollingBusy) return;

            const rows = Object.keys(this.listingPollingRows);
            if (!rows.length) {
                this.stopListingRowPolling();
                return;
            }

            this.listingPollingBusy = true;
            rows.forEach((row) => this.listingPollingRows[row].attempts++);

            try {
                const response = await this.$axios.get(
                    this.listingPollingEndpoint(),
                    { params: this.listingPollingParams(rows) },
                );
                const updatedRows = response.data?.data || [];

                updatedRows.forEach((updatedRow) => this.patchListingPolledRow(updatedRow));
                this.clearExpiredListingPollingRows();
            } catch (e) {
                console.warn('Failed to poll listing rows.', e);
                this.clearExpiredListingPollingRows();
            } finally {
                this.listingPollingBusy = false;

                if (!Object.keys(this.listingPollingRows).length) {
                    this.stopListingRowPolling();
                }
            }
        },

        listingPollingEndpoint() {
            return this.endpoint;
        },

        listingPollingParams(rows) {
            const params = new URLSearchParams();
            rows.forEach((row) => params.append('rows[]', row));
            params.set('page', 1);
            params.set('perPage', Math.max(rows.length, 1));

            return params;
        },

        patchListingPolledRow(updatedRow) {
            const key = this.listingPollingKeyForRow(updatedRow);
            const visibleRow = this.listingPollingVisibleRows[updatedRow.id]
                || this.listingPollingVisibleRows[updatedRow.path];

            if (visibleRow) {
                Object.assign(visibleRow, updatedRow);
            }

            if (key && this.isListingPollingComplete(updatedRow, this.listingPollingRows[key]?.mode)) {
                delete this.listingPollingRows[key];
                this.setListingPollingRowUpdating(visibleRow || updatedRow, false);
            } else {
                this.setListingPollingRowUpdating(visibleRow || updatedRow, true);
            }
        },

        setListingPollingRowUpdating(rowOrKey, updating) {
            const row = typeof rowOrKey === 'string'
                ? this.listingPollingVisibleRows[rowOrKey]
                : rowOrKey;

            if (row) {
                row._isPolling = updating;
            }
        },

        listingPollingKeyForRow(row) {
            return Object.keys(this.listingPollingRows).find((key) => key === row.id || key === row.path);
        },

        isListingPollingComplete(row) {
            return ['ready', 'errored'].includes(row.processing_status) || row.mirror_status === 'missing';
        },

        clearExpiredListingPollingRows() {
            Object.entries(this.listingPollingRows).forEach(([key, state]) => {
                if (state.attempts >= 120) {
                    delete this.listingPollingRows[key];
                    this.setListingPollingRowUpdating(key, false);
                }
            });
        },
    },
};
