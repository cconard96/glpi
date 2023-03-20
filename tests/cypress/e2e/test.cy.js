describe('Issue 13433', () => {
    it('load home page', () => {
        cy.loginByID('super-admin').then(() => {
            // Test navigation menu exists
            cy.get('#navbar-menu').should('exist');
        });
    });
});
