import MuxMirrorFieldtype from './components/MuxMirrorFieldtype.vue'
import MuxMirrorIndexFieldtype from './components/MuxMirrorIndexFieldtype.vue'
import MuxMirroredListing from './components/MuxMirroredListing.vue'
import MuxLibraryListing from './components/MuxLibraryListing.vue'

Statamic.$components.register('mux_mirror-fieldtype', MuxMirrorFieldtype)
Statamic.$components.register('mux_mirror-fieldtype-index', MuxMirrorIndexFieldtype)
Statamic.$components.register('mux-mirrored-listing', MuxMirroredListing)
Statamic.$components.register('mux-library-listing', MuxLibraryListing)
