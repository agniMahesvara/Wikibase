import ApiCore from '@/data-access/ApiCore';
import ApiWritingRepository from '@/data-access/ApiWritingRepository';
import BatchingApi from '@/data-access/BatchingApi';
import ServiceContainer from '@/services/ServiceContainer';
import SpecialPageReadingEntityRepository from '@/data-access/SpecialPageReadingEntityRepository';
import MwLanguageInfoRepository from '@/data-access/MwLanguageInfoRepository';
import MwWindow from '@/@types/mediawiki/MwWindow';
import MwMessagesRepository from '@/data-access/MwMessagesRepository';
import ApiRepoConfigRepository from '@/data-access/ApiRepoConfigRepository';
import DataBridgeTrackerService from '@/data-access/DataBridgeTrackerService';
import EventTracker from '@/mediawiki/facades/EventTracker';
import ApiPropertyDataTypeRepository from '@/data-access/ApiPropertyDataTypeRepository';
import ApiEntityLabelRepository from '@/data-access/ApiEntityLabelRepository';

export default function createServices( mwWindow: MwWindow, editTags: string[] ): ServiceContainer {
	const services = new ServiceContainer();

	const repoConfig = mwWindow.mw.config.get( 'wbRepo' ),
		specialEntityDataUrl = repoConfig.url + repoConfig.articlePath.replace(
			'$1',
			'Special:EntityData',
		);

	services.set( 'readingEntityRepository', new SpecialPageReadingEntityRepository(
		mwWindow.$,
		specialEntityDataUrl,
	) );

	if ( mwWindow.mw.ForeignApi === undefined ) {
		throw new Error( 'mw.ForeignApi was not loaded!' );
	}

	const repoMwApi = new mwWindow.mw.ForeignApi(
		`${repoConfig.url}${repoConfig.scriptPath}/api.php`,
	);
	const repoApi = new BatchingApi( new ApiCore( repoMwApi ) );

	services.set( 'writingEntityRepository', new ApiWritingRepository(
		repoMwApi,
		mwWindow.mw.config.get( 'wgUserName' ),
		editTags.length === 0 ? undefined : editTags,
	) );

	services.set( 'entityLabelRepository', new ApiEntityLabelRepository(
		mwWindow.mw.config.get( 'wgPageContentLanguage' ),
		repoApi,
	) );

	services.set( 'propertyDatatypeRepository', new ApiPropertyDataTypeRepository(
		repoApi,
	) );

	if ( mwWindow.$.uls === undefined ) {
		throw new Error( '$.uls was not loaded!' );
	}

	services.set( 'languageInfoRepository', new MwLanguageInfoRepository(
		mwWindow.mw.language,
		mwWindow.$.uls.data,
	) );

	services.set( 'messagesRepository', new MwMessagesRepository( mwWindow.mw.message ) );

	services.set( 'wikibaseRepoConfigRepository', new ApiRepoConfigRepository(
		repoApi,
	) );

	services.set( 'tracker', new DataBridgeTrackerService(
		new EventTracker( mwWindow.mw.track ),
	) );

	return services;
}