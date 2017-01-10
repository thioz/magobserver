<?php

return [
	'rules'=>[
		'cover'=>[
			'criteria'=>[
				'category_id'=>[3],'type'=>['grouped','simple']
			],
			'run'=>'Gracious_ProdImport_Syncer_CaseSync::syncProduct'
		],
		'notcover'=>[
			'criteria'=>[
				'category_id'=>[283],'type'=>['grouped']
			],
			'run'=>'Poeper::blaat'
		]
	]
];
