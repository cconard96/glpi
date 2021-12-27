Cypress.Commands.add('loginByID', (user_id) => {
   cy.fixture('users').then((users) => {
      const user = users[user_id];
      cy.login(user.username, user.password);
   });
});

Cypress.Commands.add('login', (username, password) => {
   cy.visit('/');
   cy.title().should('eq', 'GLPI - Authentication');
   cy.get('#login_name').type(username);
   cy.get('input[type="password"]').type(password);
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
