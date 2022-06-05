describe('Global Project Kanban', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        cy.loginByID('super-admin').then(() => {
            cy.visit('front/project.form.php?showglobalkanban=1');
        });
    });
    it('Loads Switcher', () => {
        cy.get('#kanban select[name="kanban-board-switcher"]').should('exist');
        cy.get('#kanban select[name="kanban-board-switcher"] option:first-of-type')
            .should('have.attr', 'value', -1)
            .and('have.text', 'Global');
    });
    it('Loads Search/Filter', () => {
        cy.get('#kanban div.search-input').should('exist');
        cy.get('#kanban div.search-input span.search-input-tag-input')
            .should('exist')
            .and('have.attr', 'contenteditable', 'true');
        // The original filter input should be hidden
        cy.get('#kanban input[name="filter"]').should('be.hidden');
    });
    it('Loads Column Picker', () => {
        cy.get('#kanban button.kanban-add-column').should('exist');
    });
    it('Loads Container', () => {
        const container_el = cy.get('#kanban .kanban-container');
        container_el.should('exist');
        container_el.children('.kanban-columns').should('exist');

        // Dropdowns created
        container_el.children('#kanban-add-dropdown').should('exist');
        container_el.children('#kanban-overflow-dropdown').should('exist');
        container_el.children('#kanban-item-overflow-dropdown').should('exist');
    });
});
