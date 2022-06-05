describe('Search pages display', () => {
    const testPages = (all_pages, type, category) => {
        Object.entries(all_pages).forEach(([front_file, props]) => {
            if (props.type === type && props.category && props.category === category) {
                cy.visit('front/' + front_file);
                cy.get('.search-form-container').should('exist');
                cy.get('table.search-results').should('exist');
                cy.expectNoDebugErrors();
            }
        });
    };
    it('Search pages displays without errors: Assets', () => {
        cy.loginByID('super-admin').then(() => {
            cy.fixture('front_files').then((front_files) => {
                testPages(front_files, 'search', 'assets');
            });
        });
    });

    it('Search pages displays without errors: Components', () => {
        cy.loginByID('super-admin').then(() => {
            cy.fixture('front_files').then((front_files) => {
                testPages(front_files, 'search', 'components');
            });
        });
    });

    it('Search pages displays without errors: Network', () => {
        cy.loginByID('super-admin').then(() => {
            cy.fixture('front_files').then((front_files) => {
                testPages(front_files, 'search', 'network');
            });
        });
    });

    it('Search pages displays without errors: Inventory', () => {
        cy.loginByID('super-admin').then(() => {
            cy.fixture('front_files').then((front_files) => {
                testPages(front_files, 'search', 'inventory');
            });
        });
    });

    it('Search pages displays without errors: Assistance', () => {
        cy.loginByID('super-admin').then(() => {
            cy.fixture('front_files').then((front_files) => {
                testPages(front_files, 'search', 'assistance');
            });
        });
    });

    it('Search pages displays without errors: Management', () => {
        cy.loginByID('super-admin').then(() => {
            cy.fixture('front_files').then((front_files) => {
                testPages(front_files, 'search', 'management');
            });
        });
    });

    it('Search pages displays without errors: Tools', () => {
        cy.loginByID('super-admin').then(() => {
            cy.fixture('front_files').then((front_files) => {
                testPages(front_files, 'search', 'tools');
            });
        });
    });

    it('Search pages displays without errors: Setup', () => {
        cy.loginByID('super-admin').then(() => {
            cy.fixture('front_files').then((front_files) => {
                testPages(front_files, 'search', 'setup');
            });
        });
    });

    it('Search pages displays without errors: Knowledgebase', () => {
        cy.loginByID('super-admin').then(() => {
            cy.fixture('front_files').then((front_files) => {
                testPages(front_files, 'search', 'knowledgebase');
            });
        });
    });

    it('Search pages displays without errors: Rules', () => {
        cy.loginByID('super-admin').then(() => {
            cy.fixture('front_files').then((front_files) => {
                testPages(front_files, 'search', 'rules');
            });
        });
    });

    it('Search pages displays without errors: Notifications', () => {
        cy.loginByID('super-admin').then(() => {
            cy.fixture('front_files').then((front_files) => {
                testPages(front_files, 'search', 'notifications');
            });
        });
    });
});
