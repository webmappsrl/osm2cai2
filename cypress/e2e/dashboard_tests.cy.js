describe('Dashboard Tests', () => {
  const timeout = 10000; // 10 secondi

  beforeEach(() => {
    cy.visit(Cypress.env('appUrl') + '/login', { timeout: timeout })
  })

  it('Admin user login and dashboard verification', () => {
    // Test per l'utente admin
    cy.get('#username, #email', { timeout: timeout }).type(Cypress.env('adminEmail'))
    cy.get('#password', { timeout: timeout }).type(Cypress.env('adminPassword'))
    cy.get('button[type="submit"]', { timeout: timeout }).click()

    // 1.1 Verifica nome utente
    cy.get('.username-card', { timeout: timeout }).should('contain', 'Webmapp')

    cy.get('.permessi-card', { timeout: timeout }).should('contain', 'Administrator')

    // 1.3 Verifica data ultimo login
    cy.get('.last-login-card', { timeout: timeout }).invoke('text').then((text) => {
      expect(text.trim()).to.match(/\d{4}-\d{2}-\d{2}/)
    })

    // 1.4 Verifica SAL Nazionale
    cy.get('.sal-nazionale-card', { timeout: timeout }).invoke('text').then((text) => {
      expect(text.trim()).to.match(/\d+\.\d+\s*%/)
    })

    // 1.5-1.8 Verifica SDA cards
    cy.get('.sda-card-1', { timeout: timeout }).invoke('text').then((text) => {
      expect(text.trim()).to.match(/\d+/)
    })
    cy.get('.sda-card-2', { timeout: timeout }).invoke('text').then((text) => {
      expect(text.trim()).to.match(/\d+/)
    })
    cy.get('.sda-card-3', { timeout: timeout }).invoke('text').then((text) => {
      expect(text.trim()).to.match(/\d+/)
    })
    cy.get('.sda-card-4', { timeout: timeout }).invoke('text').then((text) => {
      expect(text.trim()).to.match(/\d+/)
    })

    // Verifica che la tabella esista
    cy.get('table[data-testid="resource-table"]', { timeout: timeout }).should('exist')

    // Verifica il numero di colonne nell'intestazione
    cy.get('table[data-testid="resource-table"] thead th', { timeout: timeout }).should('have.length', 8)

    // Verifica i titoli delle colonne
    const expectedHeaders = ['Regione', '#1', '#2', '#3', '#4', '#tot', '#att', 'SAL']
    cy.get('table[data-testid="resource-table"] thead th', { timeout: timeout }).each(($th, index) => {
      cy.wrap($th).should('contain', expectedHeaders[index])
    })

    // Verifica il numero di righe nel corpo della tabella (esclusa l'intestazione)
    cy.get('table[data-testid="resource-table"] tbody tr', { timeout: timeout }).should('have.length.gt', 0)

    // Verifica il tipo di dato per ogni cella in tutte le righe
    cy.get('table[data-testid="resource-table"] tbody tr', { timeout: timeout }).each(($tr) => {
      cy.wrap($tr).within(() => {
        // Verifica che la prima cella (Regione) contenga del testo
        cy.get('td', { timeout: timeout }).eq(0).invoke('text').should('match', /^.+$/)

        // Verifica che le celle da 1 a 6 contengano numeri interi
        for (let i = 1; i <= 6; i++) {
          cy.get('td', { timeout: timeout }).eq(i).invoke('text').then((text) => {
            expect(text.trim()).to.match(/^\d+$/)
          })
        }

        // Verifica che l'ultima cella (SAL) contenga un valore percentuale o 'N/A'
        cy.get('td', { timeout: timeout }).eq(7).invoke('text').then((text) => {
          expect(text.trim()).to.match(/(^\d+\.\d+\s*%$|^N\/A$)/)
        })
      })
    })
  })

  it('National referent user login and dashboard verification', () => {
    // Test per l'utente referente nazionale
    cy.get('#username, #email', { timeout: timeout }).type(Cypress.env('referentEmail'))
    cy.get('#password', { timeout: timeout }).type(Cypress.env('referentPassword'))
    cy.get('button[type="submit"]', { timeout: timeout }).click()

    // 2.1 Verifica nome utente
    cy.get('.username-card', { timeout: timeout }).should('contain', 'Referente Nazionale')

    // 2.2 Verifica permessi
    cy.get('.permessi-card', { timeout: timeout }).should('contain', 'National Referent')
  })
})
