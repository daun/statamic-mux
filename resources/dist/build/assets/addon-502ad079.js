const v={methods:{actionStarted(){this.loading=!0},actionCompleted(t=null,e={}){this.loading=!1,t!==!1&&(this.$events.$emit("clear-selections"),this.$events.$emit("reset-action-modals"),e.success===!1?this.$toast.error(e.message||__("Action failed")):this.$toast.success(e.message||__("Action completed")),this.afterActionSuccessfullyCompleted())},afterActionSuccessfullyCompleted(){this.request()}}},g={data(){return{activeFilterBadges:{},activeFilters:{},activePreset:null,activePresetPayload:{},searchQuery:""}},computed:{activeFilterCount(){let t=Object.keys(this.activeFilters).length;return this.activeFilters.hasOwnProperty("fields")&&(t=t+Object.keys(this.activeFilters.fields).filter(e=>e!="badge").length-1),t},canSave(){return this.isDirty&&this.preferencesPrefix},isDirty(){return this.isFiltering?this.activePreset?this.activePresetPayload.query!=this.searchQuery||!_.isEqual(this.activePresetPayload.filters||{},this.activeFilters):!0:!1},isFiltering(){return!_.isEmpty(this.activeFilters)||this.searchQuery||this.activePreset},hasActiveFilters(){return this.activeFilterCount>0},searchPlaceholder(){return this.activePreset?__("Searching in: ")+this.activePresetPayload.display:__("Search")}},methods:{searchChanged(t){this.searchQuery=t},hasFields(t){for(const e in t)if(t[e])return!0;return!1},filterChanged({handle:t,values:e},s=!0){e&&this.hasFields(e)?Vue.set(this.activeFilters,t,e):Vue.delete(this.activeFilters,t),s&&this.unselectAllItems()},filtersChanged(t){this.activeFilters={};for(const e in t){const s=t[e];this.filterChanged({handle:e,values:s},!1)}this.unselectAllItems()},filtersReset(){this.activePreset=null,this.activePresetPayload={},this.searchQuery="",this.activeFilters={},this.activeFilterBadges={}},unselectAllItems(){this.$refs.dataList&&this.$refs.dataList.clearSelections()},selectPreset(t,e){this.activePreset=t,this.activePresetPayload=e,this.searchQuery=e.query,this.filtersChanged(e.filters)},autoApplyFilters(t){if(!t)return;let e={};t.filter(s=>!_.isEmpty(s.auto_apply)).forEach(s=>{e[s.handle]=s.auto_apply}),this.activeFilters=e}}},y={props:{initialPerPage:{type:Number,default(){return Statamic.$config.get("paginationSize")}}},data(){return{perPage:this.initialPerPage,page:1}},mounted(){this.setInitialPerPage()},methods:{setInitialPerPage(){this.hasPreferences&&(this.perPage=this.getPreference("per_page")||this.initialPerPage)},changePerPage(t){t=parseInt(t),(this.hasPreferences?this.setPreference("per_page",t!=this.initialPerPage?t:null):Promise.resolve()).then(s=>{this.perPage=t,this.resetPage()})},selectPage(t){this.page=t,this.$events.$emit("clear-selections")},resetPage(){this.page=1,this.$events.$emit("clear-selections")}}},P={data(){return{preferencesPrefix:null}},computed:{hasPreferences(){return this.preferencesPrefix!==null}},methods:{preferencesKey(t){return`${this.preferencesPrefix}.${t}`},getPreference(t){return this.$preferences.get(this.preferencesKey(t))},setPreference(t,e){return this.$preferences.set(this.preferencesKey(t),e)},removePreference(t,e=null){return this.$preferences.remove(this.preferencesKey(t),e)}}};function d(t,e,s,a,i,l,u,c){var r=typeof t=="function"?t.options:t;e&&(r.render=e,r.staticRenderFns=s,r._compiled=!0),a&&(r.functional=!0),l&&(r._scopeId="data-v-"+l);var o;if(u?(o=function(n){n=n||this.$vnode&&this.$vnode.ssrContext||this.parent&&this.parent.$vnode&&this.parent.$vnode.ssrContext,!n&&typeof __VUE_SSR_CONTEXT__<"u"&&(n=__VUE_SSR_CONTEXT__),i&&i.call(this,n),n&&n._registeredComponents&&n._registeredComponents.add(u)},r._ssrRegister=o):i&&(o=c?function(){i.call(this,(r.functional?this.parent:this).$root.$options.shadowRoot)}:i),o)if(r.functional){r._injectStyles=o;var m=r.render;r.render=function(f,p){return o.call(p),m(f,p)}}else{var h=r.beforeCreate;r.beforeCreate=h?[].concat(h,o):[o]}return{exports:t,options:r}}const C={mixins:[v,g,y,P],props:{initialSortColumn:String,initialSortDirection:String,initialColumns:{type:Array,default:()=>[]},filters:Array,actionUrl:String},data(){return{source:null,initializing:!0,loading:!0,items:[],columns:this.initialColumns,visibleColumns:this.initialColumns.filter(t=>t.visible),sortColumn:this.initialSortColumn,sortDirection:this.initialSortDirection,meta:null,pushQuery:!1,popping:!1}},computed:{parameterMap(){return{sort:"sortColumn",order:"sortDirection",page:"page",perPage:"perPage",search:"searchQuery",filters:"activeFilterParameters",columns:"visibleColumnParameters"}},parameters:{get(){return{...Object.fromEntries(Object.entries(this.parameterMap).map(([e,s])=>[e,this[s]]).filter(([e,s])=>s!=null&&s!=="")),...this.additionalParameters}},set(t){Object.entries(this.parameterMap).forEach(([e,s])=>{t.hasOwnProperty(e)&&(this[s]=t[e])})}},activeFilterParameters:{get(){return _.isEmpty(this.activeFilters)?null:utf8btoa(JSON.stringify(this.activeFilters))},set(t){this.activeFilters=JSON.parse(utf8atob(t))}},visibleColumnParameters:{get(){return _.isEmpty(this.visibleColumns)?null:this.visibleColumns.map(t=>t.field).join(",")},set(t){this.visibleColumns=t.split(",").map(e=>this.columns.find(s=>s.field===e))}},additionalParameters(){return{}},shouldRequestFirstPage(){return this.page>1&&this.items.length===0?(this.page=1,!0):!1}},created(){this.autoApplyFilters(this.filters),this.autoApplyState(),this.request()},mounted(){this.pushQuery&&(window.history.replaceState({parameters:this.parameters},""),window.addEventListener("popstate",this.popState))},beforeDestroy(){this.pushQuery&&window.removeEventListener("popstate",this.popState)},watch:{parameters:{deep:!0,handler(t,e){e.search===t.search&&JSON.stringify(e)!==JSON.stringify(t)&&(this.request(),this.pushState())}},loading:{immediate:!0,handler(t){this.$progress.loading(this.listingKey,t)}},searchQuery(t){this.sortColumn=null,this.sortDirection=null,this.resetPage(),this.request(),this.pushState()}},methods:{request(){if(!this.requestUrl){this.loading=!1;return}this.loading=!0,this.source&&this.source.cancel(),this.source=this.$axios.CancelToken.source(),this.$axios.get(this.requestUrl,{params:this.parameters,cancelToken:this.source.token}).then(t=>{if(this.columns=t.data.meta.columns,this.activeFilterBadges={...t.data.meta.activeFilterBadges},this.items=Object.values(t.data.data),this.meta=t.data.meta,this.shouldRequestFirstPage)return this.request();this.loading=!1,this.initializing=!1,this.afterRequestCompleted(t)}).catch(t=>{this.$axios.isCancel(t)||(this.loading=!1,this.initializing=!1,!(t.request&&!t.response)&&this.$toast.error(t.response?t.response.data.message:__("Something went wrong"),{duration:null}))})},afterRequestCompleted(t){},sorted(t,e){this.sortColumn=t,this.sortDirection=e},removeRow(t){let e=t.id,s=_.indexOf(this.rows,_.findWhere(this.rows,{id:e}));this.rows.splice(s,1),this.rows.length===0&&location.reload()},popState(t){!this.pushQuery||!t.state||(this.popping=!0,this.parameters=t.state.parameters,this.$nextTick(()=>{this.popping=!1}))},pushState(){if(!this.pushQuery||this.popping)return;const t=this.parameters,e=Object.keys(this.parameterMap),s=new URLSearchParams(Object.fromEntries(e.filter(a=>t.hasOwnProperty(a)).map(a=>[a,t[a]])));window.history.pushState({parameters:t},"","?"+s.toString())},autoApplyState(){if(!this.pushQuery||!window.location.search)return;const t=new URLSearchParams(window.location.search),e=Object.fromEntries(t.entries());this.popping=!0,this.parameters=e,this.$nextTick(()=>{this.popping=!1})}}},b=null,x=null;var w=d(C,b,x,!1,null,null,null,null);const S=w.exports,$={mixins:[S],data(){return{listingKey:"assets",preferencesPrefix:"mux.assets",requestUrl:cp_url("mux/api/assets")}},computed:{actionContext(){return{container:""}}},methods:{columnShowing(t){return this.visibleColumns.find(e=>e.field===t)},getStatusClass(t){return t.mux_disabled?"bg-transparent border border-gray-600":t.mux_id?"bg-green-600":"bg-gray-400"},getStatusLabel(t){return t.mux_disabled?__("Disabled"):t.mux_id?__("Uploaded"):__("Local")}}};var k=function(){var e=this,s=e._self._c;return s("div",[e.initializing?s("div",{staticClass:"card loading"},[s("loading-graphic")],1):e._e(),s("hr"),e._v(" COLUMNS"),s("br"),e._v(" "+e._s(e.columns)+" "),s("hr"),e._v(" VISIBLE"),s("br"),e._v(" "+e._s(e.visibleColumns)+" "),s("hr"),e.initializing?e._e():s("data-list",{ref:"datalist",attrs:{columns:e.columns,rows:e.items,sort:!1,"sort-column":e.sortColumn,"sort-direction":e.sortDirection},on:{"visible-columns-updated":function(a){e.visibleColumns=a}},scopedSlots:e._u([{key:"default",fn:function({hasSelections:a}){return s("div",{},[s("div",{staticClass:"card overflow-hidden p-0 relative"},[s("div",{staticClass:"flex flex-wrap items-center justify-between px-2 pb-2 text-sm border-b"},[s("data-list-search",{ref:"search",staticClass:"h-8 mt-2 min-w-[240px] w-full",attrs:{placeholder:e.searchPlaceholder},model:{value:e.searchQuery,callback:function(i){e.searchQuery=i},expression:"searchQuery"}}),s("data-list-column-picker",{staticClass:"ml-2 mt-2",attrs:{"preferences-key":e.preferencesKey("columns")}})],1),s("data-list-filters",{ref:"filters",attrs:{filters:e.filters,"active-preset":e.activePreset,"active-preset-payload":e.activePresetPayload,"active-filters":e.activeFilters,"active-filter-badges":e.activeFilterBadges,"active-count":e.activeFilterCount,"search-query":e.searchQuery,"is-searching":!0,"saves-presets":!0,"preferences-prefix":e.preferencesPrefix},on:{changed:e.filterChanged,saved:function(i){return e.$refs.presets.setPreset(i)},deleted:function(i){return e.$refs.presets.refreshPresets()}}}),s("div",{directives:[{name:"show",rawName:"v-show",value:e.items.length===0,expression:"items.length === 0"}],staticClass:"p-6 text-center text-gray-500",domProps:{textContent:e._s(e.__("No results"))}}),s("data-list-bulk-actions",{attrs:{url:e.actionUrl,context:e.actionContext},on:{started:e.actionStarted,completed:e.actionCompleted}}),s("div",{staticClass:"overflow-x-auto overflow-y-hidden"},[s("data-list-table",{directives:[{name:"show",rawName:"v-show",value:e.items.length,expression:"items.length"}],attrs:{loading:e.loading,"allow-bulk-actions":!0,"allow-column-picker":!0,"column-preferences-key":e.preferencesKey("columns")},on:{sorted:e.sorted},scopedSlots:e._u([{key:"cell-path",fn:function({row:i}){return[s("a",{staticClass:"title-index-field inline-flex items-center",attrs:{href:i.edit_url},on:{click:function(l){l.stopPropagation()}}},[e.columnShowing("status")?e._e():s("span",{directives:[{name:"tooltip",rawName:"v-tooltip",value:e.getStatusLabel(i),expression:"getStatusLabel(asset)"}],staticClass:"little-dot mr-2",class:e.getStatusClass(i)}),s("span",{domProps:{textContent:e._s(i.path)}})])]}},{key:"cell-status",fn:function({row:i}){return[s("div",{staticClass:"status-index-field select-none",class:`status-${i.mux_id?"published":"draft"}`,domProps:{textContent:e._s(e.getStatusLabel(i))}})]}},{key:"cell-container",fn:function({row:i}){return[s("div",{staticClass:"slug-index-field",attrs:{title:i.slug}},[e._v(e._s(i.slug))])]}},{key:"cell-size",fn:function({row:i}){return[e._v(" "+e._s(i.size)+" ")]}},{key:"cell-playtime",fn:function({row:i}){return[e._v(" "+e._s(i.playtime)+" ")]}},{key:"actions",fn:function({row:i,index:l}){return[s("dropdown-list",{attrs:{placement:"right-start"}},[i.editable?s("dropdown-item",{attrs:{text:e.__("Edit"),redirect:i.edit_url}}):e._e()],1)]}}],null,!0)})],1)],1),s("data-list-pagination",{staticClass:"mt-6",attrs:{"resource-meta":e.meta,"per-page":e.perPage,"show-totals":!0},on:{"page-selected":e.selectPage,"per-page-changed":e.changePerPage}})],1)}}],null,!1,1530343018)})],1)},F=[],A=d($,k,F,!1,null,null,null,null);const O=A.exports;const q={mixins:[Fieldtype],data(){return{details:!1,labels:{id:"Mux ID",playback_id:"Playback ID",playback_policy:"Playback Policy"}}},computed:{allowReuploads(){var t;return(t=this.config)==null?void 0:t.allow_reupload},showDetails(){var t;return(t=this.config)==null?void 0:t.show_details},isAsset(){var t;return((t=this.meta)==null?void 0:t.is_asset)||!1},isVideo(){var t;return((t=this.meta)==null?void 0:t.is_video)||!1},hasData(){return this.rows.length},hasMuxData(){return arr_get(this.field,"config.has_mux_data",!1)},rows(){const t=["id","playback_id","playback_policy"];return Object.entries(this.value||{}).filter(([e])=>t.includes(e)).sort(([e],[s])=>t.indexOf(e)-t.indexOf(s)).map(([e,s])=>{const a=this.labels[e]||e;return{key:e,value:s,label:a}})}}};var R=function(){var e=this,s=e._self._c;return s("div",[!e.isAsset||!e.isVideo?s("div",{staticClass:"help-block mb-0 flex items-center"},[s("svg-icon",{staticClass:"h-4 mr-2",attrs:{name:"hidden"}}),s("span",[e._v(" "+e._s(e.__("statamic-mux::messages.mirror_fieldtype.not_mirrored"))+": "),e.isVideo?[e._v(" "+e._s(e.__("statamic-mux::messages.mirror_fieldtype.no_asset"))+" ")]:[e._v(" "+e._s(e.__("statamic-mux::messages.mirror_fieldtype.no_video"))+" ")]],2)],1):e.hasData?s("div",[s("div",{staticClass:"help-block mb-0 flex items-center"},[s("svg-icon",{staticClass:"h-4 mr-2",attrs:{name:"synchronize"}}),s("span",{attrs:{title:this.value.id}},[e._v(" "+e._s(e.__("statamic-mux::messages.mirror_fieldtype.uploaded"))+" ")])],1),e.showDetails?s("div",{staticClass:"mux-table-wrapper mt-3"},[s("table",{staticClass:"mux-table"},[s("tbody",e._l(e.rows,function(a){return s("tr",{key:a.key},[s("th",[e._v(" "+e._s(a.label||a.key)+" ")]),s("td",[e._v(" "+e._s(a.value)+" ")])])}),0)])]):e._e(),e.allowReuploads?s("div",{staticClass:"flex items-center mt-3"},[s("label",{staticClass:"help-block flex items-center cursor-pointer font-normal",attrs:{for:"reupload-existing-asset"}},[s("span",{staticClass:"basis-6 flex items-center"},[s("input",{directives:[{name:"model",rawName:"v-model",value:e.value.reupload,expression:"value.reupload"}],attrs:{type:"checkbox",name:"reupload",id:"reupload-existing-asset"},domProps:{checked:Array.isArray(e.value.reupload)?e._i(e.value.reupload,null)>-1:e.value.reupload},on:{change:function(a){var i=e.value.reupload,l=a.target,u=!!l.checked;if(Array.isArray(i)){var c=null,r=e._i(i,c);l.checked?r<0&&e.$set(e.value,"reupload",i.concat([c])):r>-1&&e.$set(e.value,"reupload",i.slice(0,r).concat(i.slice(r+1)))}else e.$set(e.value,"reupload",u)}}})]),s("span",[e._v(e._s(e.__("statamic-mux::messages.mirror_fieldtype.reupload_on_save")))])])]):e._e()]):s("div",[s("div",{staticClass:"help-block mb-0 flex items-center"},[s("svg-icon",{staticClass:"h-4 mr-2",attrs:{name:"close"}}),s("span",[e._v(" "+e._s(e.__("statamic-mux::messages.mirror_fieldtype.not_uploaded"))+" ")])],1),e.allowReuploads?s("div",{staticClass:"flex items-center mt-3"},[s("label",{staticClass:"flex items-center cursor-pointer",attrs:{for:"upload-missing-asset"}},[s("span",{staticClass:"basis-6 flex items-center"},[s("input",{directives:[{name:"model",rawName:"v-model",value:e.value.reupload,expression:"value.reupload"}],attrs:{type:"checkbox",name:"reupload",id:"upload-missing-asset"},domProps:{checked:Array.isArray(e.value.reupload)?e._i(e.value.reupload,null)>-1:e.value.reupload},on:{change:function(a){var i=e.value.reupload,l=a.target,u=!!l.checked;if(Array.isArray(i)){var c=null,r=e._i(i,c);l.checked?r<0&&e.$set(e.value,"reupload",i.concat([c])):r>-1&&e.$set(e.value,"reupload",i.slice(0,r).concat(i.slice(r+1)))}else e.$set(e.value,"reupload",u)}}})]),s("span",{staticClass:"ml-2"},[e._v(e._s(e.__("statamic-mux::messages.mirror_fieldtype.upload_on_save")))])])]):e._e()])])},D=[],Q=d(q,R,D,!1,null,null,null,null);const E=Q.exports;Statamic.$components.register("mux-asset-listing",O);Statamic.$components.register("mux_mirror-fieldtype",E);
