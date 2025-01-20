<template>
    <div class="flex-container">
        <div class="flex-geometry" v-if="edit">
            <input ref="file" :id="field.name" type="file" :class="errorClasses" :placeholder="field.name"
                @change="updateLinestring($event)" accept=".geojson,.gpx,.kml" />
            <p v-if="hasError" class="my-2 text-danger">
                {{ firstError }}
            </p>
        </div>
    </div>
    <div id="container">
        <div :id="mapRef" class="wm-map"></div>
    </div>
</template>

<script>
import { FormField, HandlesValidationErrors } from 'laravel-nova';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import 'leaflet.fullscreen/Control.FullScreen.css';
import 'leaflet.fullscreen/Control.FullScreen.js';

const DEFAULT_TILES = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const DEFAULT_ATTRIBUTION =
  '<a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery (c) <a href="https://www.mapbox.com/">Mapbox</a>';
const DEFAULT_CENTER = [0, 0];
const DEFAULT_MINZOOM = 7;
const DEFAULT_MAXZOOM = 17;
const DEFAULT_DEFAULTZOOM = 8;
const DEFAULT_WEIGHT = 8;
let weight = DEFAULT_WEIGHT;
let lineCounter = 0;
let mapDiv = null;
let linestring = null;

function linestringOption(feature) {
  weight = weight / 2;
  lineCounter++;

  if (lineCounter == 1) {
    return {
      weight
    };
  }

  return {
    weight,
    color: '#ff0033',
  };
}
export default {
  name: 'MapMultiLineString',
  mixins: [FormField, HandlesValidationErrors],
  props: ['field', 'geojson'],
  data() {
    return {
      mapRef: `mapContainer-${Math.floor(Math.random() * 10000 + 10)}`,
      uploadFileContainer: 'uploadFileContainer',
    };
  },
  methods: {
    initMap() {
      setTimeout(() => {
        const center = this.field.center
          ? this.field.center
          : this.center
          ? this.center
          : DEFAULT_CENTER;
        const defaultZoom = this.field.defaultZoom
          ? this.field.defaultZoom
          : DEFAULT_DEFAULTZOOM;
        const linestringGeojson = this.field.geojson;
        mapDiv = L.map(this.mapRef).setView(center, defaultZoom);

        L.tileLayer(this.field.tiles ? this.field.tiles : DEFAULT_TILES, {
          attribution: this.field.attribution
            ? this.field.attribution
            : DEFAULT_ATTRIBUTION,
          maxZoom: this.field.maxZoom ? this.field.maxZoom : DEFAULT_MAXZOOM,
          minZoom: this.field.minZoom ? this.field.minZoom : DEFAULT_MINZOOM,
          id: 'mapbox/streets-v11',
        }).addTo(mapDiv);

        if (linestringGeojson != null) {
          linestring = L.geoJson(JSON.parse(linestringGeojson), {
            style: linestringOption,
          }).addTo(mapDiv);
          mapDiv.fitBounds(linestring.getBounds());
        }

        L.control
          .fullscreen({
            position: 'topleft',
            title: 'Show me the fullscreen !',
            titleCancel: 'Exit fullscreen mode',
            forceSeparateButton: true,
            forcePseudoFullscreen: false,
            fullscreenElement: false,
          })
          .addTo(mapDiv);

        L.control.scale().addTo(mapDiv);

        L.Control.Button = L.Control.extend({
          options: {
            position: 'topleft',
          },
          onAdd: function(mapDiv) {
            var container = L.DomUtil.create(
              'div',
              'leaflet-bar leaflet-control'
            );
            var button = L.DomUtil.create(
              'a',
              'leaflet-control-button',
              container
            );
            L.DomEvent.disableClickPropagation(button);
            L.DomEvent.on(button, 'click', function() {
              mapDiv.fitBounds(linestring.getBounds());
            });

            container.title = 'Title';

            return container;
          },
          onRemove: function(mapDiv) {},
        });
        var control = new L.Control.Button();
        control.addTo(mapDiv);
      }, 300);
    },
  },
  watch: {
    geojson: (gjson) => {
      if (linestring != null) {
        mapDiv.removeLayer(linestring);
      }
      if (gjson != null) {
        linestring = L.geoJSON(gjson, linestringOption).addTo(mapDiv);
        mapDiv.fitBounds(linestring.getBounds());
      }
    },
  },
  mounted() {
    this.initMap();
  },
  beforeUnmount() {
    lineCounter = 0;
    weight = DEFAULT_WEIGHT;
  },
};
</script>
