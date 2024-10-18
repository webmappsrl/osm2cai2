const { defineConfig } = require("cypress");
const dotenv = require('dotenv');

// Carica le variabili d'ambiente dal file .env
dotenv.config();

module.exports = defineConfig({
  e2e: {
    baseUrl: process.env.APP_URL || 'http://localhost:8008',
    env: {
      appUrl: process.env.APP_URL || 'http://localhost:8008',
      adminEmail: process.env.ADMIN_EMAIL || 'team@webmapp.it',
      adminPassword: process.env.ADMIN_PASSWORD,
      referentEmail: process.env.REFERENT_EMAIL || 'referenteNazionale@webmapp.it',
      referentPassword: process.env.REFERENT_PASSWORD,
    },
    setupNodeEvents(on, config) {
      // implement node event listeners here
    },
  },
});
