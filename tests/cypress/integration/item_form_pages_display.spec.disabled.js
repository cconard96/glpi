describe('Item form pages display', () => {
    const testPages = (all_pages, type, category) => {
        Object.entries(all_pages).forEach(([front_file, props]) => {
            if (props.type === type && props.category && props.category === category) {
                cy.visit('front/' + front_file);
                cy.get('form').should('exist');
                if (props.singleton) {
                    cy.get('form button[name="update"], input[type="submit"][name="update"]').should('exist');
                } else {
                    cy.get('form button[name="add"], input[type="submit"][name="add"]').should('exist');
                }
                cy.expectNoDebugErrors();
            }
        });
    };
    it('Item form pages displays without errors: Assets', () => {
        cy.loginByID('super-admin').then(() => {
            cy.fixture('front_files').then((front_files) => {
                testPages(front_files, 'item_form', 'assets');
            });
        });
    });

    it('Item form pages displays without errors: Components', () => {
        cy.loginByID('super-admin').then(() => {
            cy.fixture('front_files').then((front_files) => {
                testPages(front_files, 'item_form', 'components');
            });
        });
    });

    it('Item form pages displays without errors: Network', () => {
        cy.loginByID('super-admin').then(() => {
            cy.fixture('front_files').then((front_files) => {
                testPages(front_files, 'item_form', 'network');
            });
        });
    });

    it('Item form pages displays without errors: Inventory', () => {
        cy.loginByID('super-admin').then(() => {
            cy.fixture('front_files').then((front_files) => {
                testPages(front_files, 'item_form', 'inventory');
            });
        });
    });

    it('Item form pages displays without errors: Assistance', () => {
        cy.loginByID('super-admin').then(() => {
            cy.fixture('front_files').then((front_files) => {
                testPages(front_files, 'item_form', 'assistance');
            });
        });
    });

    it('Item form pages displays without errors: Management', () => {
        cy.loginByID('super-admin').then(() => {
            cy.fixture('front_files').then((front_files) => {
                testPages(front_files, 'item_form', 'management');
            });
        });
    });

    it('Item form pages displays without errors: Tools', () => {
        cy.loginByID('super-admin').then(() => {
            cy.fixture('front_files').then((front_files) => {
                testPages(front_files, 'item_form', 'tools');
            });
        });
    });

    it('Item form pages displays without errors: Setup', () => {
        cy.loginByID('super-admin').then(() => {
            cy.fixture('front_files').then((front_files) => {
                testPages(front_files, 'item_form', 'setup');
            });
        });
    });

    it('Item form pages displays without errors: Knowledgebase', () => {
        cy.loginByID('super-admin').then(() => {
            cy.fixture('front_files').then((front_files) => {
                testPages(front_files, 'item_form', 'knowledgebase');
            });
        });
    });

    it('Item form pages displays without errors: Rules', () => {
        cy.loginByID('super-admin').then(() => {
            cy.fixture('front_files').then((front_files) => {
                testPages(front_files, 'item_form', 'rules');
            });
        });
    });

    it('Item form pages displays without errors: Notifications', () => {
        cy.loginByID('super-admin').then(() => {
            cy.fixture('front_files').then((front_files) => {
                testPages(front_files, 'item_form', 'notifications');
            });
        });
    });
});
