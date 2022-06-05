/**
 * Log in to GLPI as a specific user based on a user id (key in user fixtures)
 *
 * @function cy.loginByID
 * @param {string} user_id User ID from the users fixture
 */
Cypress.Commands.add('loginByID', (user_id) => {
    cy.fixture('users').then((users) => {
        const user = users[user_id];
        cy.login(user.username, user.password);
    });
});

/**
 * Log in to GLPI as a specific user based on a username and password
 *
 * @function cy.login
 * @param {string} username
 * @param {string} password
 */
Cypress.Commands.add('login', (username, password) => {
    cy.visit('/');
    cy.title().should('eq', 'Authentication - GLPI');
    cy.get('#login_name').type(username);
    cy.get('input[type="password"]').type(password);
    cy.get('button[type="submit"]').click();
    cy.url().should('include', '/front/central.php');
});

/**
 * Expect no GLPI debug mode alerts to be showing on the page
 *
 * @function cy.expectNoDebugErrors
 */
Cypress.Commands.add('expectNoDebugErrors', () => {
    cy.get('.alert.glpi-debug-alert').should('not.exist');
});

/**
 * Fill the specified form with the specified data, then submit the form.
 *
 * @function cy.fillAndSubmitForm
 * @param {string} form_id
 * @param {{}} data
 */
Cypress.Commands.add('fillAndSubmitForm', (form_id, data) => {
    cy.get(`#${form_id}`).within(() => {
        for (const [key, value] of Object.entries(data)) {
            cy.get(`*[name="${key}"]`).type(value);
        }
        cy.get('button[type="submit"]').click();
    });
});
