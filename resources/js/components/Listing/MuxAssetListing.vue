<template>
  <div>

    <div v-if="initializing" class="card loading">
      <loading-graphic />
    </div>

    <hr>
    COLUMNS<br>
    {{ columns }}
    <hr>
    VISIBLE<br>
    {{ visibleColumns }}
    <hr>

    <data-list
      v-if="!initializing"
      ref="datalist"
      :columns="columns"
      :rows="items"
      :sort="false"
      :sort-column="sortColumn"
      :sort-direction="sortDirection"
      @visible-columns-updated="visibleColumns = $event"
    >
      <div slot-scope="{ hasSelections }">
        <div class="card overflow-hidden p-0 relative">
          <div class="flex flex-wrap items-center justify-between px-2 pb-2 text-sm border-b">
            <data-list-search class="h-8 mt-2 min-w-[240px] w-full" ref="search" v-model="searchQuery" :placeholder="searchPlaceholder" />
            <data-list-column-picker class="ml-2 mt-2" :preferences-key="preferencesKey('columns')" />
          </div>

          <data-list-filters
              ref="filters"
              :filters="filters"
              :active-preset="activePreset"
              :active-preset-payload="activePresetPayload"
              :active-filters="activeFilters"
              :active-filter-badges="activeFilterBadges"
              :active-count="activeFilterCount"
              :search-query="searchQuery"
              :is-searching="true"
              :saves-presets="true"
              :preferences-prefix="preferencesPrefix"
              @changed="filterChanged"
              @saved="$refs.presets.setPreset($event)"
              @deleted="$refs.presets.refreshPresets()"
          />

          <div v-show="items.length === 0" class="p-6 text-center text-gray-500" v-text="__('No results')" />

          <data-list-bulk-actions
              :url="actionUrl"
              :context="actionContext"
              @started="actionStarted"
              @completed="actionCompleted"
          />

          <div class="overflow-x-auto overflow-y-hidden">
              <data-list-table
                  v-show="items.length"
                  :loading="loading"
                  :allow-bulk-actions="true"
                  :allow-column-picker="true"
                  :column-preferences-key="preferencesKey('columns')"
                  @sorted="sorted"
              >
                  <template slot="cell-path" slot-scope="{ row: asset }">
                      <a class="title-index-field inline-flex items-center" :href="asset.edit_url" @click.stop>
                          <span class="little-dot mr-2" v-tooltip="getStatusLabel(asset)" :class="getStatusClass(asset)" v-if="! columnShowing('status')" />
                          <span v-text="asset.path" />
                      </a>
                  </template>
                  <template slot="cell-status" slot-scope="{ row: asset }">
                      <div class="status-index-field select-none" :class="`status-${asset.mux_id ? 'published' : 'draft'}`" v-text="getStatusLabel(asset)" />
                  </template>
                  <!-- <template slot="cell-mux_id" slot-scope="{ row: asset }">
                      {{ asset.mux?.mux_id }}
                  </template> -->
                  <template slot="cell-container" slot-scope="{ row: asset }">
                      <div class="slug-index-field" :title="asset.slug">{{ asset.slug }}</div>
                  </template>
                  <template slot="cell-size" slot-scope="{ row: asset }">
                      {{ asset.size }}
                  </template>
                  <template slot="cell-playtime" slot-scope="{ row: asset }">
                      {{ asset.playtime }}
                  </template>
                  <template slot="actions" slot-scope="{ row: asset, index }">
                      <dropdown-list placement="right-start">
                          <dropdown-item :text="__('Edit')" :redirect="asset.edit_url" v-if="asset.editable" />
                          <!-- <div class="divider" v-if="asset.actions.length" />
                          <data-list-inline-actions
                              :item="asset.id"
                              :url="actionUrl"
                              :actions="asset.actions"
                              @started="actionStarted"
                              @completed="actionCompleted"
                          /> -->
                      </dropdown-list>
                  </template>
              </data-list-table>
          </div>
        </div>

        <data-list-pagination
            class="mt-6"
            :resource-meta="meta"
            :per-page="perPage"
            :show-totals="true"
            @page-selected="selectPage"
            @per-page-changed="changePerPage"
        />
      </div>
    </data-list>

  </div>
</template>

<script>
import Listing from '../../../../vendor/statamic/cms/resources/js/components/Listing.vue';

export default {

  mixins: [Listing],

  data() {
    return {
      listingKey: 'assets',
      preferencesPrefix: 'mux.assets',
      requestUrl: cp_url('mux/api/assets'),
    };
  },

  computed: {
    actionContext() {
        return { container: '' };
    },
  },

  methods: {

    columnShowing(column) {
        return this.visibleColumns.find(c => c.field === column);
    },

    getStatusClass(asset) {
        if (asset.mux_disabled) {
            return 'bg-transparent border border-gray-600';
        } else if (asset.mux_id) {
            return 'bg-green-600';
        } else {
            return 'bg-gray-400';
        }
    },

    getStatusLabel(asset) {
        if (asset.mux_disabled) {
            return __('Disabled');
        } else if (asset.mux_id) {
            return __('Uploaded');
        } else {
            return __('Local');
        }
    },

  }
};
</script>
