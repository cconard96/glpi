describe('Global layout', () => {
    it('Navbar', () => {
        cy.loginByID('super-admin').then(() => {
            // Test navigation menu exists
            cy.get('#navbar-menu').should('exist');
            // Test navigation menu items exist
            cy.fixture('nav_menu').then(expected_layout => {
                cy.fixture('front_files').then(front_files => {
                    cy.get('#navbar-menu > ul > li').should(top_level_items => {
                        Object.keys(expected_layout).forEach(function(key) {
                            const expected_sub_items = expected_layout[key];
                            const top_level_item_link = top_level_items.find('a:contains(' + key + ')');
                            const top_level_item = top_level_item_link.closest('li');
                            expect(top_level_item).to.exist;

                            const sub_items = top_level_item.find('.dropdown-menu .dropdown-item');
                            expect(sub_items.length).to.be.gte(expected_sub_items.length);
                            expected_sub_items.forEach(function(expected_sub_item) {
                                let found = false;
                                // Loop through sub items
                                sub_items.each((i, sub_item) => {
                                    // get sub item text
                                    const sub_item_text = Cypress.$(sub_item).text().trim();
                                    // Check if sub item text matches expected
                                    if (sub_item_text === expected_sub_item) {
                                        found = true;
                                        // Check if sub item has an icon
                                        const sub_item_icon = Cypress.$(sub_item).find('> i');
                                        expect(sub_item_icon.length).to.be.eq(1);
                                        // expect link href to exist as a key in front_files
                                        const href = sub_item.getAttribute('href');
                                        //strip '/front/' from start of href and ignore everything after '.php'
                                        const href_key = href.replace(/^\/front\//, '').split('.php')[0] + '.php';
                                        expect(href_key).to.be.oneOf(Object.keys(front_files));
                                    }
                                });
                                expect(found).to.be.true;
                            });
                        });
                    });
                    cy.expectNoDebugErrors();
                });
            });
        });
    });

    it('Header', () => {
        cy.loginByID('super-admin').then(() => {
            cy.get('header').then(header => {
                expect(header).to.exist;
                // Expect header breadcrumb to include Home and nothing else
                expect(header.find('.breadcrumb > li').length).to.be.eq(1);
                expect(header.find('form[role="search"]').length).to.be.eq(1);
                expect(header.find('div.user-menu').length).to.be.eq(1);
            });
            cy.expectNoDebugErrors();
        });
    });
});
