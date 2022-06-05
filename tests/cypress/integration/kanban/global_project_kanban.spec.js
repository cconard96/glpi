describe('Global Project Kanban', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        cy.loginByID('super-admin').then(() => {
            cy.visit('front/project.form.php?showglobalkanban=1');
        });
    });
    describe('Loads', () => {
        it('Loads switcher', () => {
            cy.get('#kanban select[name="kanban-board-switcher"]').should('exist');
            cy.get('#kanban select[name="kanban-board-switcher"] option:first-of-type')
                .should('have.attr', 'value', -1)
                .and('have.text', 'Global');
        });
        it('Loads search/filter box', () => {
            cy.get('#kanban div.search-input').should('exist');
            cy.get('#kanban div.search-input span.search-input-tag-input')
                .should('exist')
                .and('have.attr', 'contenteditable', 'true');
            // The original filter input should be hidden
            cy.get('#kanban input[name="filter"]').should('be.hidden');
        });
        it('Loads column picker', () => {
            cy.get('#kanban button.kanban-add-column').should('exist');
        });
    });
});
