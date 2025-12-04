<template>
    <div class="feature-collection-map-container">
        <div ref="mapContainer" class="map-container"></div>

        <!-- Tooltip overlay -->
        <div ref="tooltipElement" class="map-tooltip" v-show="tooltipVisible">
            {{ tooltipText }}
        </div>

        <!-- Default Popup - simple with name and link (only if no custom popup component) -->
        <Teleport to="body">
            <div v-if="showPopup && !popupComponent" class="fixed inset-0 z-[60] flex items-center justify-center p-4"
                role="dialog" aria-modal="true">
                <div
                    class="relative z-20 bg-white dark:bg-gray-800 rounded-lg shadow-xl overflow-hidden w-full max-w-md">
                    <!-- Header -->
                    <div class="bg-primary-500 dark:bg-primary-600 px-6 py-4">
                        <h3 class="text-lg font-semibold text-white">{{ popupTitle }}</h3>
                    </div>

                    <!-- Content -->
                    <div class="px-6 py-4">
                        <p class="text-gray-600 dark:text-gray-300 text-sm">
                            Clicca sul pulsante per visualizzare i dettagli.
                        </p>
                    </div>

                    <!-- Footer with buttons -->
                    <div
                        class="px-6 py-4 bg-gray-100 dark:bg-gray-700 flex justify-end items-center gap-3 border-t border-gray-200 dark:border-gray-600">
                        <button type="button" @click="closePopup"
                            style="background: white; color: #333; border: 1px solid #ccc; padding: 8px 16px; border-radius: 4px; font-weight: bold; cursor: pointer;">
                            Chiudi
                        </button>
                        <a v-if="currentFeatureId" :href="getResourceLink()" target="_blank"
                            style="background: #0ea5e9; color: white; padding: 8px 16px; border-radius: 4px; font-weight: bold; text-decoration: none; cursor: pointer;">
                            Vai alla risorsa
                        </a>
                    </div>
                </div>
            </div>
            <!-- Backdrop -->
            <div v-if="showPopup && !popupComponent" class="fixed inset-0 z-[55] bg-gray-500/75 dark:bg-gray-900/75"
                @click="closePopup">
            </div>
        </Teleport>
    </div>
</template>

<script>
import { ref, onMounted, onUnmounted, watch } from 'vue';

// OpenLayers imports
import Map from 'ol/Map';
import View from 'ol/View';
import TileLayer from 'ol/layer/Tile';
import VectorLayer from 'ol/layer/Vector';
import VectorSource from 'ol/source/Vector';
import OSM from 'ol/source/OSM';
import GeoJSON from 'ol/format/GeoJSON';
import Overlay from 'ol/Overlay';
import { Style, Fill, Stroke, Circle as CircleStyle } from 'ol/style';
import { fromLonLat } from 'ol/proj';
import { defaults as defaultControls } from 'ol/control';
import { defaults as defaultInteractions } from 'ol/interaction';

export default {
    name: 'FeatureCollectionMap',

    props: {
        geojsonUrl: {
            type: String,
            required: true
        },
        height: {
            type: Number,
            default: 500
        },
        showZoomControls: {
            type: Boolean,
            default: true
        },
        mouseWheelZoom: {
            type: Boolean,
            default: true
        },
        dragPan: {
            type: Boolean,
            default: true
        },
        padding: {
            type: Number,
            default: 50
        },
        resourceName: {
            type: String,
            default: 'poles'
        },
        popupComponent: {
            type: String,
            default: null
        }
    },

    emits: ['feature-click', 'map-ready', 'popup-open', 'popup-close'],

    setup(props, { emit }) {
        const mapContainer = ref(null);
        const map = ref(null);
        const vectorSource = ref(null);
        const geojsonData = ref(null);
        const featuresMap = ref({});

        // Tooltip state
        const tooltipElement = ref(null);
        const tooltipOverlay = ref(null);
        const tooltipVisible = ref(false);
        const tooltipText = ref('');

        // Popup state
        const showPopup = ref(false);
        const popupTitle = ref('');
        const popupData = ref({});
        const currentFeatureId = ref(null);
        const currentFeatureProperties = ref({});

        // Get link to Nova resource
        const getResourceLink = () => {
            if (!currentFeatureId.value) return '#';
            return `/resources/${props.resourceName}/${currentFeatureId.value}`;
        };

        // Style function for features
        const getFeatureStyle = (feature) => {
            const featureProps = feature.getProperties();
            const geometryType = feature.getGeometry().getType();

            if (geometryType === 'Point' || geometryType === 'MultiPoint') {
                return new Style({
                    image: new CircleStyle({
                        radius: featureProps.pointRadius || 6,
                        fill: new Fill({
                            color: featureProps.pointFillColor || 'rgba(255, 0, 0, 0.8)'
                        }),
                        stroke: new Stroke({
                            color: featureProps.pointStrokeColor || 'rgba(255, 255, 255, 1)',
                            width: featureProps.pointStrokeWidth || 2
                        })
                    })
                });
            }

            return new Style({
                stroke: new Stroke({
                    color: featureProps.strokeColor || 'rgba(0, 0, 255, 1)',
                    width: featureProps.strokeWidth || 3
                }),
                fill: new Fill({
                    color: featureProps.fillColor || 'rgba(0, 0, 255, 0.3)'
                })
            });
        };

        // Load GeoJSON data
        const loadGeoJSON = async () => {
            console.log('Loading GeoJSON from:', props.geojsonUrl);
            try {
                const response = await fetch(props.geojsonUrl);

                if (!response.ok) {
                    console.error('GeoJSON fetch failed:', response.status, response.statusText);
                    return;
                }

                const data = await response.json();
                console.log('GeoJSON loaded:', data);
                geojsonData.value = data;

                if (data.features) {
                    data.features.forEach(feature => {
                        if (feature.properties && feature.properties.id) {
                            featuresMap.value[String(feature.properties.id)] = feature;
                        }
                    });
                }

                const format = new GeoJSON();
                const features = format.readFeatures(data, {
                    featureProjection: 'EPSG:3857'
                });

                vectorSource.value.clear();
                vectorSource.value.addFeatures(features);

                if (features.length > 0) {
                    const extent = vectorSource.value.getExtent();
                    map.value.getView().fit(extent, {
                        padding: [props.padding, props.padding, props.padding, props.padding],
                        maxZoom: 17,
                        duration: 500
                    });
                }

                emit('map-ready', { map: map.value, features, geojson: data, featuresMap: featuresMap.value });
            } catch (error) {
                console.error('Error loading GeoJSON:', error);
            }
        };

        // Handle feature click - can be overridden in child components
        const handleFeatureClick = (feature, featureProps) => {
            const clickAction = featureProps.clickAction || (featureProps.link ? 'link' : 'none');

            switch (clickAction) {
                case 'popup':
                    openPopup(feature, featureProps);
                    break;

                case 'link':
                    if (featureProps.link) {
                        window.open(featureProps.link, '_blank');
                    }
                    emit('feature-click', { feature, properties: featureProps, action: 'link' });
                    break;

                default:
                    emit('feature-click', { feature, properties: featureProps, action: 'none' });
            }
        };

        // Open popup - can be overridden in child components
        const openPopup = (feature, featureProps) => {
            currentFeatureId.value = featureProps.id;
            currentFeatureProperties.value = featureProps;
            popupTitle.value = featureProps.name || featureProps.tooltip || `#${featureProps.id}`;
            popupData.value = featureProps.dem || {};

            showPopup.value = true;

            emit('popup-open', {
                feature,
                properties: featureProps,
                id: featureProps.id,
                dem: featureProps.dem,
                featuresMap: featuresMap.value
            });

            emit('feature-click', { feature, properties: featureProps, action: 'popup' });
        };

        // Handle map click
        const handleClick = (event) => {
            const pixel = event.pixel;
            const features = map.value.getFeaturesAtPixel(pixel);

            if (features && features.length > 0) {
                const feature = features[0];
                const featureProps = feature.getProperties();
                handleFeatureClick(feature, featureProps);
            }
        };

        // Handle pointer move for cursor change and tooltip
        const handlePointerMove = (event) => {
            const pixel = event.pixel;
            const feature = map.value.forEachFeatureAtPixel(pixel, (f) => f);

            if (feature) {
                map.value.getTargetElement().style.cursor = 'pointer';
                const featureProps = feature.getProperties();
                const tooltip = featureProps.tooltip || featureProps.name;

                if (tooltip && tooltipOverlay.value) {
                    tooltipText.value = tooltip;
                    tooltipVisible.value = true;
                    tooltipOverlay.value.setPosition(event.coordinate);
                }
            } else {
                map.value.getTargetElement().style.cursor = '';
                tooltipVisible.value = false;
            }
        };

        // Close popup
        const closePopup = () => {
            showPopup.value = false;
            emit('popup-close', { id: currentFeatureId.value });
            currentFeatureId.value = null;
            currentFeatureProperties.value = {};
            popupData.value = {};
        };

        // Handle ESC key
        const handleKeydown = (event) => {
            if (event.key === 'Escape' && showPopup.value) {
                closePopup();
            }
        };

        // Initialize map
        onMounted(() => {
            vectorSource.value = new VectorSource();

            const vectorLayer = new VectorLayer({
                source: vectorSource.value,
                style: getFeatureStyle
            });

            map.value = new Map({
                target: mapContainer.value,
                layers: [
                    new TileLayer({
                        source: new OSM()
                    }),
                    vectorLayer
                ],
                view: new View({
                    center: fromLonLat([12.5, 42.5]),
                    zoom: 6
                }),
                controls: defaultControls({
                    zoom: props.showZoomControls
                }),
                interactions: defaultInteractions({
                    mouseWheelZoom: props.mouseWheelZoom,
                    dragPan: props.dragPan
                })
            });

            // Create tooltip overlay
            tooltipOverlay.value = new Overlay({
                element: tooltipElement.value,
                positioning: 'bottom-center',
                offset: [0, -10],
                stopEvent: false
            });
            map.value.addOverlay(tooltipOverlay.value);

            map.value.on('click', handleClick);
            map.value.on('pointermove', handlePointerMove);
            document.addEventListener('keydown', handleKeydown);

            loadGeoJSON();
        });

        // Cleanup
        onUnmounted(() => {
            document.removeEventListener('keydown', handleKeydown);
            if (map.value) {
                map.value.setTarget(null);
                map.value = null;
            }
        });

        // Watch for geojsonUrl changes
        watch(() => props.geojsonUrl, () => {
            loadGeoJSON();
        });

        return {
            // Props (for template)
            popupComponent: props.popupComponent,
            // Refs
            mapContainer,
            tooltipElement,
            tooltipVisible,
            tooltipText,
            // Popup state
            showPopup,
            popupTitle,
            popupData,
            currentFeatureId,
            currentFeatureProperties,
            // Methods
            closePopup,
            getResourceLink,
            // Exposed for child components
            map,
            vectorSource,
            featuresMap,
            openPopup,
            loadGeoJSON
        };
    }
};
</script>

<style scoped>
.feature-collection-map-container {
    position: relative;
    width: 100%;
}

.map-container {
    width: 100%;
    height: v-bind('height + "px"');
    border-radius: 4px;
    overflow: hidden;
}

.map-tooltip {
    position: absolute;
    background-color: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    pointer-events: none;
    z-index: 100;
}
</style>
