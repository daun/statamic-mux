import { registerIconSet } from '@statamic/cms/ui';

import MuxMirrorFieldtype from './components/MuxMirrorFieldtype.vue'
import MuxMirrorIndexFieldtype from './components/MuxMirrorIndexFieldtype.vue'
import MirroredAssetsPage from './pages/MirroredAssets.vue'
import MuxLibraryPage from './pages/MuxLibrary.vue'
import EmptyPage from './pages/Empty.vue'

Statamic.$components.register('mux_mirror-fieldtype', MuxMirrorFieldtype)
Statamic.$components.register('mux_mirror-fieldtype-index', MuxMirrorIndexFieldtype)
Statamic.$inertia.register('MuxAssetsPage', MirroredAssetsPage)
Statamic.$inertia.register('MuxLibraryPage', MuxLibraryPage)
Statamic.$inertia.register('EmptyPage', EmptyPage)

registerIconSet('mux', import.meta.glob('../svg/icons/*.svg', { query: '?raw', import: 'default' }));
