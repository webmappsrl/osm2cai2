import DetailField from './components/DetailField.vue';
import FormField from './components/FormField.vue';
import IndexField from './components/IndexField.vue';

Nova.booting((app) => {
    app.component('detail-signage-arrows', DetailField);
    app.component('form-signage-arrows', FormField);
    app.component('index-signage-arrows', IndexField);
});


