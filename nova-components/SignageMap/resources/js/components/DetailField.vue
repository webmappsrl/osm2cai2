<template>
    <PanelItem :index="index" :field="field">
        <template #value>
            <!-- Usa FeatureCollectionMap dal wm-package -->
            <FeatureCollectionMap :geojson-url="geojsonUrl" :height="field.height || 500"
                :show-zoom-controls="field.showZoomControls !== false"
                :mouse-wheel-zoom="field.mouseWheelZoom !== false" :drag-pan="field.dragPan !== false"
                :popup-component="'signage-map'" @feature-click="handleFeatureClick" @map-ready="handleMapReady"
                @popup-open="handlePopupOpen" @popup-close="handlePopupClose" />

            <!-- Custom Signage Popup -->
            <Teleport to="body">
                <div v-if="showPopup" class="fixed inset-0 z-[9999] flex items-center justify-center p-4" role="dialog"
                    aria-modal="true">
                    <!-- Backdrop -->
                    <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75" @click="closePopup"></div>

                    <!-- Modal -->
                    <div
                        class="relative z-10 bg-white dark:bg-gray-800 rounded-lg shadow-xl overflow-hidden w-full max-w-md">
                        <!-- Header -->
                        <div class="bg-primary-500 dark:bg-primary-600 px-6 py-4">
                            <h3 class="text-lg font-semibold text-white">{{ popupTitle }}</h3>
                        </div>

                        <!-- Content -->
                        <div class="px-6 py-4">
                            <p class="text-gray-600 dark:text-gray-300 text-sm">
                                Palo selezionato: <strong>{{ popupTitle }}</strong>
                            </p>
                            <p class="text-gray-500 dark:text-gray-400 text-xs mt-2">
                                Clicca sul pulsante per visualizzare i dettagli del palo.
                            </p>
                        </div>

                        <!-- Footer with buttons -->
                        <div
                            class="px-6 py-4 bg-gray-100 dark:bg-gray-700 flex justify-end items-center gap-3 border-t border-gray-200 dark:border-gray-600">
                            <button type="button" @click="closePopup"
                                class="bg-white hover:bg-gray-100 text-gray-700 border border-gray-300 px-4 py-2 rounded font-medium cursor-pointer">
                                Chiudi
                            </button>
                            <a :href="poleLink" target="_blank"
                                class="bg-primary-500 hover:bg-primary-600 text-white px-4 py-2 rounded font-medium no-underline cursor-pointer inline-block">
                                Vai al Palo
                            </a>
                        </div>
                    </div>
                </div>
            </Teleport>
        </template>
    </PanelItem>
</template>

<script>
// Importa FeatureCollectionMap dal wm-package
import FeatureCollectionMap from '../../../../../wm-package/src/Nova/Fields/FeatureCollectionMap/resources/js/components/FeatureCollectionMap.vue';

export default {
    name: 'SignageMapDetailField',

    components: {
        FeatureCollectionMap
    },

    props: ['index', 'resource', 'resourceName', 'resourceId', 'field'],

    data() {
        return {
            showPopup: false,
            popupTitle: '',
            currentPoleId: null
        };
    },

    computed: {
        geojsonUrl() {
            if (this.field.geojsonUrl) {
                return this.field.geojsonUrl;
            }

            const modelName = this.resourceName;
            const id = this.resourceId || (this.resource && this.resource.id && this.resource.id.value);
            const baseUrl = `/nova-vendor/feature-collection-map/${modelName}/${id}`;

            console.log('SignageMap URL:', baseUrl);

            if (this.field.demEnrichment) {
                return `${baseUrl}?dem_enrichment=1`;
            }

            return baseUrl;
        },

        poleLink() {
            if (!this.currentPoleId) return '#';
            return `/resources/poles/${this.currentPoleId}`;
        }
    },

    mounted() {
        console.log('SignageMap DetailField mounted');
        document.addEventListener('keydown', this.handleKeydown);

        // Ascolta gli eventi Nova come fa il wm-package
        Nova.$on('signage-map:popup-open', this.onNovaPopupOpen);
        Nova.$on('signage-map:popup-close', this.onNovaPopupClose);
    },

    beforeUnmount() {
        document.removeEventListener('keydown', this.handleKeydown);
        Nova.$off('signage-map:popup-open', this.onNovaPopupOpen);
        Nova.$off('signage-map:popup-close', this.onNovaPopupClose);
    },

    methods: {
        handleFeatureClick(event) {
            console.log('SignageMap: Feature clicked:', event);
        },

        handleMapReady(event) {
            console.log('SignageMap: Map ready:', event);
        },

        handlePopupOpen(event) {
            console.log('SignageMap: Popup open, emitting Nova event:', event);
            // Emetti evento Nova come fa il wm-package
            Nova.$emit('signage-map:popup-open', {
                ...event,
                popupComponent: 'signage-map'
            });
        },

        handlePopupClose(event) {
            console.log('SignageMap: Popup close, emitting Nova event:', event);
            Nova.$emit('signage-map:popup-close', event);
        },

        // Handler per eventi Nova
        onNovaPopupOpen(event) {
            console.log('SignageMap: Nova popup-open received:', event);
            if (event.popupComponent === 'signage-map') {
                this.currentPoleId = event.id;
                this.popupTitle = event.properties?.name || event.properties?.tooltip || `Palo #${event.id}`;
                this.showPopup = true;
            }
        },

        onNovaPopupClose(event) {
            console.log('SignageMap: Nova popup-close received:', event);
            this.closePopup();
        },

        closePopup() {
            this.showPopup = false;
            this.currentPoleId = null;
            this.popupTitle = '';
        },

        handleKeydown(event) {
            if (event.key === 'Escape' && this.showPopup) {
                this.closePopup();
            }
        }
    }
};
</script>
