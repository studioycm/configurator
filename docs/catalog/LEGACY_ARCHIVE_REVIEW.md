# T12 retired POC archival review

Approved by the user and applied on 2026-09-24 in the isolated rebuild checkout. The initial broad operation had been rejected before this exact inventory was reviewed; that earlier hold is resolved. Source hashes below were checked before applying 82 moves and 36 copies.

The current admin provider explicitly registers only Groups, Products, Configurators, Attributes, Values and Options; the demo page and old resource routes are absent. The new public runtime imports only the new Product/Group and canonical definition models. The files below belong to the retired POC, including parts, saved configurations and attachment ownership that are deferred in this rebuild.

Applied change: preserve these files under `tests/Fixtures/Legacy/`, rewrite their internal namespaces to `Tests\Fixtures\Legacy`, and load their archived schema/routes only inside the retained historical tests. Preserve all test declarations and assertions; keep new public/Preview tests on the new engine. Restore the matching old evaluator from HEAD only into the test archive. Copy the 19 old domain migrations into that test-only fixture path, never into the application migration path. No dependency, user data, active database or source-workspace change is included.

Completed validation: syntax/Pint, retained historical tests, new catalog/admin tests, full SQLite regression suite, production route/dependency scan and MySQL new-domain checks. No retired class is required by an active new route. Full SQLite: 245 passed / 1370 assertions. Guarded MySQL: 94 passed / 595 assertions. All historical tests remain; archived migrations/routes load only for opted-in testing SQLite `:memory:` fixtures.

| Source-relative file | Approved action | Source SHA-256 |
| --- | --- | --- |
| `app/Casts/NormalizedIntArrayCast.php` | move to test-only archive | `489204af3a52f55e900a62d06589c199a861e7d21f1ec9024b826063f8e5697c` |
| `app/ConfigProfileScope.php` | move to test-only archive | `27cb162fdbb7b62de795260712d69a911d1e3d05ece603062eb9689de727f41e` |
| `app/DTO/ConfigOptionDTO.php` | move to test-only archive | `1e7861bb00e1c4036bc6bcb6f5aad66921d118e4a489b025129b85b7d8ad7c57` |
| `app/DTO/ConfigStageDTO.php` | move to test-only archive | `4191910b5e10e7f35e645ba5539152bd940f06248bc35c5b2db908a183321b20` |
| `app/Filament/Pages/ConfigEngineDemo.php` | move to test-only archive | `8ae196dd1d7d84752810ad0df0b30b5d57198f677a3b9225420930d71d2278af` |
| `app/Filament/Resources/CatalogGroups/CatalogGroupResource.php` | copy matching source into archive | `e1f3a27e42ccc0c09ff2b5440b32f17ad4ed437be7750b0f9b7f273054479c02` |
| `app/Filament/Resources/CatalogGroups/Pages/CreateCatalogGroup.php` | copy matching source into archive | `01b2982397c850bcf9a15ce9edbdfdecd1ef7056b8ee610f39cf8778795942b6` |
| `app/Filament/Resources/CatalogGroups/Pages/EditCatalogGroup.php` | copy matching source into archive | `ff0131e558329561b2b0a58d7c707be8399057b3a0828f62da5bbc5eabaeb05e` |
| `app/Filament/Resources/CatalogGroups/Pages/ListCatalogGroups.php` | copy matching source into archive | `ba12c084011a34ccad4dd790dd217f3f924820cff7d08527546f3255cbfda11f` |
| `app/Filament/Resources/CatalogGroups/RelationManagers/FileAttachmentsRelationManager.php` | copy matching source into archive | `00299a1aab730029a60b5bb7100429fbb9480e69f68fc9c44a02fe44cc646ca8` |
| `app/Filament/Resources/CatalogGroups/RelationManagers/ProductProfilesRelationManager.php` | copy matching source into archive | `5d39de16a49d43fba56dbe4355237cb2449b7ff6901dfe3e2fa259401ab88ffe` |
| `app/Filament/Resources/CatalogGroups/Schemas/CatalogGroupForm.php` | copy matching source into archive | `b34719c1dae85b17759b05fb62083e26672557210bbcae7d2c393bca4dfce096` |
| `app/Filament/Resources/CatalogGroups/Tables/CatalogGroupsTable.php` | copy matching source into archive | `20c50e1b0411bc08f81c0a2f88a71aa5560f70370b7ad8c6d86306492130a201` |
| `app/Filament/Resources/ConfigAttributes/ConfigAttributeResource.php` | copy matching source into archive | `a5ad67f45e3afb1acdc9c10863dadf09d6a3d1dfc5f9f242cb2b3f2c6d3e6c3d` |
| `app/Filament/Resources/ConfigAttributes/Pages/CreateConfigAttribute.php` | copy matching source into archive | `59a752a1e231b92e548edfa5c585f391dc961816a60e284181d6924b753e647a` |
| `app/Filament/Resources/ConfigAttributes/Pages/EditConfigAttribute.php` | copy matching source into archive | `acfd15e06934a5d5069fd78b457015412dbc51b93079c02e00d691c22642ace1` |
| `app/Filament/Resources/ConfigAttributes/Pages/ListConfigAttributes.php` | copy matching source into archive | `c770534819b1d90e72ea3c75c2f0906cd10d2a9b5e655a1637b93648b1342803` |
| `app/Filament/Resources/ConfigAttributes/RelationManagers/OptionsRelationManager.php` | move to test-only archive | `c7dfb67076796bf9c2820d7f6e6958aaec1ed998b704e080cc7735eb20799b9e` |
| `app/Filament/Resources/ConfigAttributes/Schemas/ConfigAttributeForm.php` | copy matching source into archive | `d8811f8500d225cb5955effad46678f27af73d510ff0219f732c9cf1f2d3a5c0` |
| `app/Filament/Resources/ConfigAttributes/Tables/ConfigAttributesTable.php` | copy matching source into archive | `7f6bfdf0573f6fadddea0006f835be5c9198c4e9b7d308656b218bd03ccc1001` |
| `app/Filament/Resources/ConfigOptions/ConfigOptionResource.php` | copy matching source into archive | `248c7d08dd1eeaa909065933cb72039640f31276c53b35e0399cd612c750cfb7` |
| `app/Filament/Resources/ConfigOptions/Pages/CreateConfigOption.php` | copy matching source into archive | `b6b2486d932130d6e93ff9e1ce57f0334aee640d212e83d972005f73ad282da9` |
| `app/Filament/Resources/ConfigOptions/Pages/EditConfigOption.php` | copy matching source into archive | `9a7b7d6316f8730c4d9ac91aa67d89250d2eb67dd1ad27317261028881bf2155` |
| `app/Filament/Resources/ConfigOptions/Pages/ListConfigOptions.php` | copy matching source into archive | `287c1aa6c81abf4d60039f1fc4f4b3dfae907f95ec1e7ad30a1822437b216d80` |
| `app/Filament/Resources/ConfigOptions/RelationManagers/OptionRulesRelationManager.php` | move to test-only archive | `fb5b093141ded15649a8bda7ebc1c1bddaf6260d893e7ac7d108b25cc5f322e3` |
| `app/Filament/Resources/ConfigOptions/Schemas/ConfigOptionForm.php` | copy matching source into archive | `d415f0b92775be662f9eacf7fa7ce6944819a9fa323e755d08063b03fbc55480` |
| `app/Filament/Resources/ConfigOptions/Tables/ConfigOptionsTable.php` | copy matching source into archive | `ff992fc28d4be80fb145c7290e5fb24d9af2f2f987489bc51585d5fc5d890548` |
| `app/Filament/Resources/ConfigProfiles/ConfigProfileResource.php` | copy matching source into archive | `e98fe40db73865906b0bd9b5989415878e6cf446ddc40408cdb384b38fca8b19` |
| `app/Filament/Resources/ConfigProfiles/Pages/CreateConfigProfile.php` | copy matching source into archive | `c2f4f91e20870be26d6c6a345a3ae0609a42b75dad924c4270256bd95a8629b5` |
| `app/Filament/Resources/ConfigProfiles/Pages/EditConfigProfile.php` | copy matching source into archive | `48419942e8af22e51ca75a296953a493f6a450117cd458c89dbdd2b6f0884f81` |
| `app/Filament/Resources/ConfigProfiles/Pages/ListConfigProfiles.php` | copy matching source into archive | `087d2a73c3f212d99038030be268009e71e2efaee293a55700152e10d4e1af7b` |
| `app/Filament/Resources/ConfigProfiles/RelationManagers/AttributesRelationManager.php` | copy matching source into archive | `2e6569901000cd6ee984ed0e68a9ac837dbd00f1b750de8c1e7b0dc1206bbba1` |
| `app/Filament/Resources/ConfigProfiles/RelationManagers/RulesRelationManager.php` | move to test-only archive | `ab6aad76d7e25285e03a99ea6f47ed4e298927fb52075f5c71bc29c6fdd15f0d` |
| `app/Filament/Resources/ConfigProfiles/Schemas/ConfigProfileForm.php` | copy matching source into archive | `1cd19f64db9a7810c16c5532b4c7438a63a934ba84b351ff1f0c7d6da7be7c9e` |
| `app/Filament/Resources/ConfigProfiles/Tables/ConfigProfilesTable.php` | copy matching source into archive | `18b8d0c78adfcd33b8e06c3332bb6bae6d6a260c4982fd1d079a790e661afec2` |
| `app/Filament/Resources/ConfigurationParts/ConfigurationPartResource.php` | move to test-only archive | `564ae23c7debf96b933d4f934e3834ea844b0e99211b5afba47cdfd8283e826e` |
| `app/Filament/Resources/ConfigurationParts/Pages/CreateConfigurationPart.php` | move to test-only archive | `aaa0ca70fd5aaec3f098cba84ef232d6e96a2739269da1685fb6428400d97f09` |
| `app/Filament/Resources/ConfigurationParts/Pages/EditConfigurationPart.php` | move to test-only archive | `389758f093872533695a4528659c79f4ee276e45e54d0109ad61eaf6dfd884e9` |
| `app/Filament/Resources/ConfigurationParts/Pages/ListConfigurationParts.php` | move to test-only archive | `449b5865e3cc371cb7c7a78d19e9381bc13b7bf0bde03923322156eb1111cad4` |
| `app/Filament/Resources/ConfigurationParts/RelationManagers/FileAttachmentsRelationManager.php` | move to test-only archive | `e10a782d646c7d8a9d3481b340507d7fdb06dd1a3f542b43e228ed72c582b8f9` |
| `app/Filament/Resources/ConfigurationParts/Schemas/ConfigurationPartForm.php` | move to test-only archive | `bd1d70d492cbe0b9062ad58c70588ecbaafc50bc26115710ef4598e72e1caa71` |
| `app/Filament/Resources/ConfigurationParts/Tables/ConfigurationPartsTable.php` | move to test-only archive | `6c045b6538b230e681d7225b39ed1eeb2a5a393f6cd3435f944ee4736f8aa111` |
| `app/Filament/Resources/ConfigurationSpecifications/ConfigurationSpecificationResource.php` | move to test-only archive | `5927adc7f6fd6fe2242f422f67d456d3d600311b2ddb3bb8f972a69a6b8b12c8` |
| `app/Filament/Resources/ConfigurationSpecifications/Pages/CreateConfigurationSpecification.php` | move to test-only archive | `4198e231222e4e7cd00a99f1ca438b59ed4d8bd4a635ac111269e34de9a43fdd` |
| `app/Filament/Resources/ConfigurationSpecifications/Pages/EditConfigurationSpecification.php` | move to test-only archive | `b573fbd1be765ab388010f73c7a19ae11337f2d074d93cb923a04a0c639a9dae` |
| `app/Filament/Resources/ConfigurationSpecifications/Pages/ListConfigurationSpecifications.php` | move to test-only archive | `f1e62936d26645cf5979dd4ceb134becb5a07a55e0d4aa062371158d793e7917` |
| `app/Filament/Resources/ConfigurationSpecifications/Schemas/ConfigurationSpecificationForm.php` | move to test-only archive | `5fca8fb974166565b699768a89ae8dd3c5723055922f366e87f243a0d6a52669` |
| `app/Filament/Resources/ConfigurationSpecifications/Tables/ConfigurationSpecificationsTable.php` | move to test-only archive | `920475be3058752bd5c4717607eab43ccffa379bfce69b39a2514d83466f8762` |
| `app/Filament/Resources/FileAttachments/FileAttachmentResource.php` | move to test-only archive | `ecc716b8679f9b7869dcf9aa9c85ba2479e5fad28b5107e2260cf45638c745b1` |
| `app/Filament/Resources/FileAttachments/Pages/CreateFileAttachment.php` | move to test-only archive | `7bcd79b6e491e8ed89e10322078ee112dbd92e4f72ff129a2b86b6e12baa403f` |
| `app/Filament/Resources/FileAttachments/Pages/EditFileAttachment.php` | move to test-only archive | `17effd9fb5f4522d445373250426975a3ffd93daee7f84fcced58f8bdd319834` |
| `app/Filament/Resources/FileAttachments/Pages/ListFileAttachments.php` | move to test-only archive | `1991346ef12b5cc200103ed02c74d213480286daf4e03a51caddc8f49e4d66a7` |
| `app/Filament/Resources/FileAttachments/Schemas/FileAttachmentForm.php` | move to test-only archive | `31c72803a2829652b991925378f9223ef4b1eda68dd4bac31666eaea0ac8542c` |
| `app/Filament/Resources/FileAttachments/Tables/FileAttachmentsTable.php` | move to test-only archive | `2635f42bde46b06209ec9baabef34ebd8c86233a969e93cbdd0acc4dfaa20078` |
| `app/Filament/Resources/OptionRules/OptionRuleResource.php` | move to test-only archive | `fbcbe58cb2170d346b665748ecf67d17ef2a88bbdf6abdc095ea01ac04e836b5` |
| `app/Filament/Resources/OptionRules/Pages/CreateOptionRule.php` | move to test-only archive | `135dd8127d89c2238886d2bccb3ed9785876c4352baa0e55e101d6ddead7d903` |
| `app/Filament/Resources/OptionRules/Pages/EditOptionRule.php` | move to test-only archive | `39e99e72e17abc6572dc30c0c530fc34d04b359730922ffd8f9c8d303685f033` |
| `app/Filament/Resources/OptionRules/Pages/ListOptionRules.php` | move to test-only archive | `c03b0adb9b1c078edd808c27b21ac30a2990b02da38efe2e2d1c8f3b48c56381` |
| `app/Filament/Resources/OptionRules/Schemas/OptionRuleForm.php` | move to test-only archive | `7c040e5872ee5853d8fe1a55bd53bb2ff9c16248585b37a2352d418ae0eaf9c3` |
| `app/Filament/Resources/OptionRules/Tables/AllowedOptionsTable.php` | move to test-only archive | `be0b2fae88c2d73fdb2a7605e815fca7ef5e7aebdc51f7a309d99ec9c003ab68` |
| `app/Filament/Resources/OptionRules/Tables/OptionRulesTable.php` | move to test-only archive | `cc8c96ee9f846e3c406c2e4e6a8f45659131278c99fafa5a7855fc6e892f47f4` |
| `app/Filament/Resources/Parts/Pages/CreatePart.php` | move to test-only archive | `4f24ef69ca0f676e01f1b9ecd0ee480802d6a6fa4a544b24dfa1e0d6760793e2` |
| `app/Filament/Resources/Parts/Pages/EditPart.php` | move to test-only archive | `2da5dba343066d3f5cc7c70c85c8bc427aae8128c138babd0e64398903f4f9a7` |
| `app/Filament/Resources/Parts/Pages/ListParts.php` | move to test-only archive | `f8aa40c2506d5fe21bb7f89bca4767d82da8107a25673cf1a76d338aa27e53e4` |
| `app/Filament/Resources/Parts/PartResource.php` | move to test-only archive | `1c2f1437dc37991f3d0ace2e02175dca723904b342402e87374f1a75267b56fc` |
| `app/Filament/Resources/Parts/RelationManagers/ConfigurationPartsRelationManager.php` | move to test-only archive | `7c5fd0dd651b100d8e0b982c64f94d8fd493bd44fe739ad204edefb3caf2f35b` |
| `app/Filament/Resources/Parts/RelationManagers/FileAttachmentsRelationManager.php` | move to test-only archive | `9e4d34f0800d245bda7ef1692c3083328b4e825364fd764fbca2351ed19f0a44` |
| `app/Filament/Resources/Parts/Schemas/PartForm.php` | move to test-only archive | `93e2910f819058a9477cc0c2ccba66b48521eba1e30f3538700a6197ee7aac88` |
| `app/Filament/Resources/Parts/Tables/PartsTable.php` | move to test-only archive | `d73817b08e71a6c8710c6a6e3a0ff71237a2a123ac2332bdabd4488261d31e10` |
| `app/Filament/Resources/ProductConfigurations/Pages/CreateProductConfiguration.php` | move to test-only archive | `b50e03ba20dcc5cf8a98dc62d5efb00241284a130d9949a7954ce06dae376656` |
| `app/Filament/Resources/ProductConfigurations/Pages/EditProductConfiguration.php` | move to test-only archive | `63eb63da9aaaf4b8242470f715f896c2783a5ca57b3d44fb9d0566c3123ffa93` |
| `app/Filament/Resources/ProductConfigurations/Pages/ListProductConfigurations.php` | move to test-only archive | `2b6894de2c275e005301238ee6e6f06eb0bf2f6cf339091f9a2ea8bb7f91137f` |
| `app/Filament/Resources/ProductConfigurations/ProductConfigurationResource.php` | move to test-only archive | `09b98a4cd97ace33942bc476638af0f801abf4404fab8b23737ba5926ffddf91` |
| `app/Filament/Resources/ProductConfigurations/RelationManagers/ConfigurationPartsRelationManager.php` | move to test-only archive | `30a8dc662ffe1fca974f5d5797314423c3e5be5fb1285093a1844733ccfd417d` |
| `app/Filament/Resources/ProductConfigurations/RelationManagers/ConfigurationSpecificationsRelationManager.php` | move to test-only archive | `25fbf855e26fe6a0ce0580f90fcd9a440fb3ad9cb3265fd82f42ffe07dffe660` |
| `app/Filament/Resources/ProductConfigurations/RelationManagers/FileAttachmentsRelationManager.php` | move to test-only archive | `b83d296553ff0f32bf94ccb21590581ee7ee37372fc7a2e24cf9caed2cee2c43` |
| `app/Filament/Resources/ProductConfigurations/Schemas/ProductConfigurationForm.php` | move to test-only archive | `d8fe4050ab03b9bc427129acd11105c4864fa7b789c4757b5d77c0e328c1a321` |
| `app/Filament/Resources/ProductConfigurations/Tables/ProductConfigurationsTable.php` | move to test-only archive | `b157f1b51af002093ee9b66dc53fa97966b09b691603a1b4628bc4e9fdd155ab` |
| `app/Filament/Resources/ProductProfiles/Pages/CreateProductProfile.php` | copy matching source into archive | `dd378dd5578a96554f7f99d4ef7e17ad8135cd0d500360d0d1fdac8d5a7183b2` |
| `app/Filament/Resources/ProductProfiles/Pages/EditProductProfile.php` | copy matching source into archive | `fac7244eea80f36b2faf4aae0f4514f4059e59d3c3da806a4492fd76f5df0c7f` |
| `app/Filament/Resources/ProductProfiles/Pages/ListProductProfiles.php` | copy matching source into archive | `05024358f0cf872a14882f56f9fbad536b49360a3ae7986e4f31494feb87bc5f` |
| `app/Filament/Resources/ProductProfiles/ProductProfileResource.php` | copy matching source into archive | `b4724926e8978cde8fac49c8469cdaef96112e35747e3a376dc890a54e42231b` |
| `app/Filament/Resources/ProductProfiles/RelationManagers/ConfigProfilesRelationManager.php` | copy matching source into archive | `6b023d700db702f5a8309a5ff4d5ebfc5fac6b6b476db0fdbfc7405fe5df37f7` |
| `app/Filament/Resources/ProductProfiles/RelationManagers/FileAttachmentsRelationManager.php` | copy matching source into archive | `2a1b42a596c9144a45fd7a6deebe131e1bc25d3355e6fa02f9657e43e341205b` |
| `app/Filament/Resources/ProductProfiles/RelationManagers/ProductConfigurationsRelationManager.php` | copy matching source into archive | `19648c4b4ef7a68fe7905cd5e8b6ccf90bbf1685f962bae6bfba0f2bb7d315ae` |
| `app/Filament/Resources/ProductProfiles/Schemas/ProductProfileForm.php` | copy matching source into archive | `829d10c61919be86f4178323756655beac53816b73035aa3f5c4f53def060036` |
| `app/Filament/Resources/ProductProfiles/Tables/ProductProfilesTable.php` | copy matching source into archive | `27f9de683f91d09e1562be9b6a944af968c07ae1c9b12a7d0fdd2ed1c1a24e18` |
| `app/FileAttachmentType.php` | move to test-only archive | `d74a44b2dec00c4fa88c50a5c475431beee2cd2a0263f15cd993cee4929acf3c` |
| `app/Models/CatalogGroup.php` | move to test-only archive | `9d2239208a451f00063863093b7ded5d3a22b5ee06c0580df440e5a85ce8755c` |
| `app/Models/ConfigAttribute.php` | move to test-only archive | `1ab58441cec761b050b4f1cc8ffbf7053e03b28f0bccfb2c972e0c2b15c3ce91` |
| `app/Models/ConfigOption.php` | move to test-only archive | `47d62acce4e0bea45706d8e1e74679fb834e73f3ad73138aa6da244b564a64bb` |
| `app/Models/ConfigProfile.php` | move to test-only archive | `2f4fe872f177eb9ab123b75e101a42dbabf1ad9a9f6ef277c92d051c129c5cd3` |
| `app/Models/ConfigurationPart.php` | move to test-only archive | `3e93d4029daaf17f7d2cc1712133310741178c82fd4d1499899c7190c5441382` |
| `app/Models/ConfigurationSpecification.php` | move to test-only archive | `8590127ad03d9a4093b8c2a699f636c7802d5abfd1e9d4bb789d7bae24e1bbaf` |
| `app/Models/FileAttachment.php` | move to test-only archive | `8e35c787d2d8855da762729a6666301e3bb53f1f634f4117a9ce999ff16b34f9` |
| `app/Models/OptionRule.php` | move to test-only archive | `956fd5221712692e3b7a91e9c37b072cb244754bbf9cabeb0779a41e8a9d68fc` |
| `app/Models/Part.php` | move to test-only archive | `827fcd310ec95d00ed1052cbeb84ac35b4228992ca25ef950fbdf891fc3f684a` |
| `app/Models/ProductConfiguration.php` | move to test-only archive | `85308e70364f4f8fb38eabe78ff16a4324db16cce9ff93e0adc4e02eabda8ed8` |
| `app/Models/ProductProfile.php` | move to test-only archive | `acd7fbbf4638cd53e25fc4c85056d1554a5bbdbd8481b9cd28a8e188739abe01` |
| `app/OptionRuleDependencyType.php` | move to test-only archive | `3e796eef44b0ade4d79cc9e9e3c9e980af0a25205ee780bf029281d790c9c43f` |
| `database/factories/CatalogGroupFactory.php` | move to test-only archive | `47879b245fbca550f7710e4d9f67977cddcebdc3a3e3f8fb9867beaa96c0a3cf` |
| `database/factories/ConfigAttributeFactory.php` | move to test-only archive | `b94d8d55f92c28301eb3732dc60859073a3e38238d13589c9df89f10f63645eb` |
| `database/factories/ConfigOptionFactory.php` | move to test-only archive | `04562957835d2192415f53e7d2cb72fb4fd71400fc3cfe7f0af9e19102b0fbb4` |
| `database/factories/ConfigProfileFactory.php` | move to test-only archive | `3c7b0fd7477a536e355a1fad62b3ded2a3ec7211652c26b3c3de4c30a9eefdc9` |
| `database/factories/ConfigurationPartFactory.php` | move to test-only archive | `17e46419f7e1222b70a840b053d4d532d9bd0f6e6976a38f105421e3fa73d1da` |
| `database/factories/ConfigurationSpecificationFactory.php` | move to test-only archive | `a3736e3ff91b5719948532ddb878e3fe1b9c11720a29f500791cf8276af1d3fc` |
| `database/factories/FileAttachmentFactory.php` | move to test-only archive | `f5d004a6f1457f914313e2ad330a3e9688d5d5f300758366f3a36ddaf87e70e3` |
| `database/factories/OptionRuleFactory.php` | move to test-only archive | `b78c6ad01d2f6f0e78d33360291e241fb82a6e66c881f199169b301516b56240` |
| `database/factories/PartFactory.php` | move to test-only archive | `68ea461ac6303de9cf2d23b3d0f53dd6d13673a6c460b95cc5727eec4aa80d1a` |
| `database/factories/ProductConfigurationFactory.php` | move to test-only archive | `4bbc53088b7798a5d6d5edaffb2d7b4297f98183d0963231bcf773e9b94b47e4` |
| `database/factories/ProductProfileFactory.php` | move to test-only archive | `6e9bdfad3a73af02cc35d21baaa3680d65e0ea99f67e8fb85649c3abe8f3cb3c` |
| `database/seeders/ConfiguratorDemoSeeder.php` | move to test-only archive | `b46bcc4d798c274d113b8ff68d72ae47ff685f578f53620e9e2f34daeee160d6` |
| `database/seeders/ConfiguratorRuntimeMetadataSeeder.php` | move to test-only archive | `aeca29cadc629d7d8baab62f3839017109c0c291df571f1fb2b5a1a6ac390ea7` |
| `resources/views/filament/pages/config-engine-demo/partials/file-links.blade.php` | move to test-only archive | `72d2ac3e192986e9762037825f89ce97a5bbe2e090ac9f258a953302f93617a2` |
| `resources/views/filament/pages/config-engine-demo/partials/image-grid.blade.php` | move to test-only archive | `a3d3d69675ba52d0b171d23baf732408ba70486028724f5f864c07bdb65e3389` |
| `resources/views/filament/pages/config-engine-demo/partials/image-trigger.blade.php` | move to test-only archive | `c913f66eb6df1939f767f9e6dcd72e3ceb1aad61ebf611b1e77547313db2c205` |
| `resources/views/filament/pages/config-engine-demo/partials/modal-image.blade.php` | move to test-only archive | `a8f3aaf29678eb2d43f5348751c67bdf2070d82e452ff093f2f6ac3536ca41c1` |
| `resources/views/filament/pages/config-engine-demo.blade.php` | move to test-only archive | `eb9ded35ba9d56e26704eef02c0ce31a94150ed68e4a8093acb1fd114199510e` |
