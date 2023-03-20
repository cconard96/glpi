// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add('login', (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add('drag', { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add('dismiss', { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This will overwrite an existing command --
// Cypress.Commands.overwrite('visit', (originalFn, url, options) => { ... })

Cypress.Commands.add('loginByID', (user_id) => {
    cy.fixture('users').then((users) => {
        const user = users[user_id];
        cy.login(user.username, user.password);
    });
});

Cypress.Commands.add('login', (username, password) => {
    cy.visit('/');
    cy.title().should('eq', 'Authentication - GLPI');
    cy.get('#login_name').type(username);
    cy.get('input[type="password"]').type(password);
    // If there is a select named auth, select the "local" option
    cy.get('select[name="auth"]').select('local', { force: true });
    cy.get('button[type="submit"]').click();
    cy.url().should('include', '/front/central.php');
});

Cypress.Commands.add('expectNoDebugErrors', () => {
    cy.get('.alert.glpi-debug-alert').should('not.exist');
});

Cypress.Commands.add('fillAndSubmitForm', (form_id, data) => {
    cy.get(`#${form_id}`).within(() => {
        for (const [key, value] of Object.entries(data)) {
            cy.get(`*[name="${key}"]`).type(value);
        }
        cy.get('button[type="submit"]').click();
    });
});
