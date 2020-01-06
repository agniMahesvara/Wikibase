import App from '@/presentation/App.vue';
import { createStore } from '@/store';
import { STORE_INIT, HELP_LINK_SET, FEEDBACK_LINK_SET } from '@/store/actionTypes';
import { HookHandler } from '@/HookHandler';

export function launch( hookHandler: HookHandler, helpLink: string, feedbackLink: string ): void {
	const store = createStore();
	const guids: string[] = [];
	document.querySelectorAll( '.wikibase-statementview' ).forEach( ( element ) => {
		const id = element.getAttribute( 'id' );
		const headingElement = element.querySelector( '.wikibase-statementview-references-heading' );
		if ( headingElement && id ) {
			guids.push( id );
			const appElement = headingElement.appendChild( document.createElement( 'div' ) );
			appElement.setAttribute( 'class', 'wikibase-tainted-references-container' );
			new App( { store, data: { id } } ).$mount( appElement );
		}
	} );
	store.dispatch( STORE_INIT, guids );
	store.dispatch( HELP_LINK_SET, helpLink );
	store.dispatch( FEEDBACK_LINK_SET, feedbackLink );
	hookHandler.addStore( store );
}