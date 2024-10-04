describe('Dashboard Tests', () => {
  beforeEach(() => {
    cy.visit('http://localhost:8008/login')
  })

  it('Admin user login and dashboard verification', () => {
    // Test per l'utente admin
    cy.get('#email').type(Cypress.env('adminEmail'))
    cy.get('#password').type(Cypress.env('adminPassword'))
    cy.get('button[type="submit"]').click()

    // 1.1 Verifica nome utente
    cy.get('.username-card').should('contain', 'Webmapp')

    // 1.2 Verifica permessi
    cy.get('.permessi-card').should('contain', 'Administrator')

    // 1.3 Verifica data ultimo login
    cy.get('.last-login-card').invoke('text').then((text) => {
      expect(text.trim()).to.match(/\d{4}-\d{2}-\d{2}/)
    })

    // 1.4 Verifica SAL Nazionale
    cy.get('.sal-nazionale-card').invoke('text').then((text) => {
      expect(text.trim()).to.match(/\d+\.\d+\s*%/)
    })

    // 1.5-1.8 Verifica SDA cards
    cy.get('.sda-card-1').invoke('text').then((text) => {
      expect(text.trim()).to.match(/\d+/)
    })
    cy.get('.sda-card-2').invoke('text').then((text) => {
      expect(text.trim()).to.match(/\d+/)
    })
    cy.get('.sda-card-3').invoke('text').then((text) => {
      expect(text.trim()).to.match(/\d+/)
    })
    cy.get('.sda-card-4').invoke('text').then((text) => {
      expect(text.trim()).to.match(/\d+/)
    })

    // Verifica che la tabella esista
    cy.get('table[data-testid="resource-table"]').should('exist')

    // Verifica il numero di colonne nell'intestazione
    cy.get('table[data-testid="resource-table"] thead th').should('have.length', 8)

    // Verifica i titoli delle colonne
    const expectedHeaders = ['Regione', '#1', '#2', '#3', '#4', '#tot', '#att', 'SAL']
    cy.get('table[data-testid="resource-table"] thead th').each(($th, index) => {
      cy.wrap($th).should('contain', expectedHeaders[index])
    })

    // Verifica il numero di righe nel corpo della tabella (esclusa l'intestazione)
    cy.get('table[data-testid="resource-table"] tbody tr').should('have.length.gt', 0)

    // Verifica il tipo di dato per ogni cella in tutte le righe
    cy.get('table[data-testid="resource-table"] tbody tr').each(($tr) => {
      cy.wrap($tr).within(() => {
        // Verifica che la prima cella (Regione) contenga del testo
        cy.get('td').eq(0).invoke('text').should('match', /^.+$/)

        // Verifica che le celle da 1 a 6 contengano numeri interi
        for (let i = 1; i <= 6; i++) {
          cy.get('td').eq(i).invoke('text').then((text) => {
            expect(text.trim()).to.match(/^\d+$/)
          })
        }

        // Verifica che l'ultima cella (SAL) contenga un valore percentuale o 'N/A'
        cy.get('td').eq(7).invoke('text').then((text) => {
          expect(text.trim()).to.match(/(^\d+\.\d+\s*%$|^N\/A$)/)
        })
      })
    })
  })

  it('National referent user login and dashboard verification', () => {
    // Test per l'utente referente nazionale
    cy.get('#email').type(Cypress.env('referentEmail'))
    cy.get('#password').type(Cypress.env('referentPassword'))
    cy.get('button[type="submit"]').click()

    // 2.1 Verifica nome utente
    cy.get('.username-card').should('contain', 'Referente Nazionale')

    // 2.2 Verifica permessi
    cy.get('.permessi-card').should('contain', 'National Referent')
  })
})
