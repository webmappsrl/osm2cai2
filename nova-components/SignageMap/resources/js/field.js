import DetailField from './components/DetailField.vue';
import FormField from './components/FormField.vue';
import IndexField from './components/IndexField.vue';

Nova.booting((app) => {
    app.component('detail-signage-map', DetailField);
    app.component('form-signage-map', FormField);
    app.component('index-signage-map', IndexField);
});




