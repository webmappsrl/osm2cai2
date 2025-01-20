import IndexField from './components/IndexField'
import DetailField from './components/DetailField'
import FormField from './components/FormField'
import MapComponent from './components/MapComponent'

Nova.booting((app, store) => {
  app.component('osm2cai-map-multi-linestring', MapComponent)
  app.component('index-osm2cai-map-multi-linestring', IndexField)
  app.component('detail-osm2cai-map-multi-linestring', DetailField)
  app.component('form-osm2cai-map-multi-linestring', FormField)
})
