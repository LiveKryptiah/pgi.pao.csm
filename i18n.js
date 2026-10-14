/**
 * CSM Multi-Language Controller
 * Supports: Tagalog (fil), English (en), and Ilokano (ilo) — Clean, Solo Language Mode
 * Province of Isabela — Office of the Provincial Assessor
 */

(function() {
    'use strict';

    const STORAGE_KEY = 'csm_lang';

    const translations = {
        fil: {
            pageTitle: "Pagsusukat ng Kasiyahan ng Kliyente (CSM) — Panlalawigang Tagapagtasa ng Isabela",
            agencyTitle: "PAMAHALAANG PANLALAWIGAN NG ISABELA",
            officeTitle: "TANGGAPAN NG PANLALAWIGANG TAGAPAGTASA",
            csmSub: "Pagsusukat ng Kasiyahan ng Kliyente (CSM)",
            
            // Section 1
            sec1Title: "1. Impormasyon ng Kliyente",
            sec1Sub: "Punan ang mga batayang impormasyon ng transaksiyon",
            sec1Badge: "Bahagi 1",
            labelDate: "Petsa",
            labelClientType: "Uri ng Kliyente",
            optClientPlaceholder: "Pumili ng Uri ng Kliyente...",
            optClientCitizen: "Mamamayan",
            optClientBusiness: "Negosyo",
            optClientGov: "Gobyerno",
            labelGender: "Kasarian",
            optGenderPlaceholder: "Pumili ng Kasarian...",
            optGenderFemale: "Babae",
            optGenderMale: "Lalake",
            labelAge: "Edad",
            optAgePlaceholder: "Pumili ng Edad...",
            optAge19: "19 pababa",
            optAge20: "20-34 taong gulang",
            optAge35: "35-49 taong gulang",
            optAge50: "50-64 taong gulang",
            optAge65: "65 at pataas",
            labelRegion: "Rehiyon",
            labelProvince: "Lalawigan",
            optProvIsabela: "Isabela",
            optProvOutside: "Ibang Lalawigan",
            labelMuni: "Lungsod/Bayan",
            optMuniPlaceholder: "Pumili ng Lungsod/Bayan...",
            labelTrans: "Uri ng Transaksiyon",
            optTransPlaceholder: "Pumili ng Uri ng Transaksiyon...",

            // Section 2
            sec2Title: "2. Citizen's Charter (CC)",
            sec2Sub: "Kaalaman at karanasan sa Citizen's Charter",
            sec2Badge: "Bahagi 2",
            labelCC1: "Alin sa mga sumusunod ang naglalarawan sa iyong kaalaman sa CC?",
            optCC1Placeholder: "Pumili ng sagot...",
            optCC1_1: "1. Alam ko ang CC at nakita ko ito sa napuntahang opisina.",
            optCC1_2: "2. Alam ko ang CC pero hindi ko ito nakita sa napuntahang opisina.",
            optCC1_3: "3. Nalaman ko ang CC nang makita ko ito sa napuntahang opisina.",
            optCC1_4: "4. Hindi ko alam kung ano ang CC at wala akong nakita sa napuntahang opisina.",
            labelCC2: "Kung alam ang CC, masasabi mo ba na ang CC ng napuntahang opisina ay:",
            optCC2Placeholder: "Pumili ng sagot...",
            optCC2_1: "Madaling Makita",
            optCC2_2: "Medyo Madaling Makita",
            optCC2_3: "Mahirap Makita",
            optCC2_4: "Hindi Makita",
            optCC2_NA: "Hindi Naaangkop",
            labelCC3: "Kung alam ang CC, gaano nakakatulong ang CC sa transaksiyon mo?",
            optCC3Placeholder: "Pumili ng sagot...",
            optCC3_1: "Sobrang Nakakatulong",
            optCC3_2: "Nakakatulong naman",
            optCC3_3: "Hindi Nakatulong",
            optCC3_NA: "Hindi Naaangkop",

            // Section 3
            sec3Title: "3. Sukat ng Kalidad ng Serbisyo (SQD)",
            sec3Sub: "Piliin ang pinaka-angkop na marka para sa bawat aspeto",
            sec3Badge: "Bahagi 3",
            ratingGuideTitle: "Gabay sa Pagmamarka:",
            legend1: "Lubos na di sumasang-ayon",
            legend2: "Di sumasang-ayon",
            legend3: "Neutral",
            legend4: "Sumasang-ayon",
            legend5: "Lubos na sumasang-ayon",
            legendNA: "Hindi Naaangkop",

            sqd: {
                sqd0: "SQD0. Nasiyahan ako sa serbisyo na aking natanggap.",
                sqd1: "SQD1. Makatwiran ang oras na aking ginugol para sa pagproseso ng aking transaksyon.",
                sqd2: "SQD2. Ang opisina ay sumusunod sa mga kinakailangang dokumento at mga hakbang batay sa impormasyong ibinigay.",
                sqd3: "SQD3. Ang mga hakbang sa pagproseso, kasama na ang pagbayad ay madali at simple lamang.",
                sqd4: "SQD4. Mabilis at madali akong nakahanap ng impormasyon tungkol sa aking transaksiyon mula sa opisina o website.",
                sqd5: "SQD5. Nagbayad ako ng makatwirang halaga para sa aking transaksiyon. (Kung libre, piliin ang N/A)",
                sqd6: "SQD6. Pakiramdam ko ay patas ang opisina sa lahat, o 'walang palakasan', sa aking transaksiyon.",
                sqd7: "SQD7. Magalang akong trinato ng mga tauhan, at sila ay handang tumulong sa akin.",
                sqd8: "SQD8. Nakuha ko ang kinakailangan ko mula sa tanggapan, o kung tinanggihan man, ito ay sapat na ipinaliwanag."
            },

            likertLabels: {
                "1": "Lubos na di sumasang-ayon",
                "2": "Hindi sumasang-ayon",
                "3": "Walang kinikilingan",
                "4": "Sumasang-ayon",
                "5": "Lubos na sumasang-ayon",
                "6": "Hindi Naaangkop"
            },

            // Section 4
            sec4Title: "4. Mga Suhestiyon at Komento",
            sec4Sub: "Ibahagi ang iyong mga puna upang mapabuti pa ang aming serbisyo",
            sec4Badge: "Bahagi 4",
            labelSuggestions: "Mga suhestiyon kung paano pa mapapabuti ang aming serbisyo:",
            suggestionsPlaceholder: "Isulat dito ang iyong komento, obserbasyon, o mungkahi upang higit pang mapaganda ang aming serbisyo...",
            labelEmail: "Email Address:",
            emailPlaceholder: "pangalan@email.com",
            emailNote: "Kung nais mong makatanggap ng tugon mula sa amin ukol sa iyong puna.",

            // Submit & Footer
            btnSubmitText: "Ipasa ang Pagsusuri",
            submittingText: "Ipinapasa...",
            footerGov: "Republika ng Pilipinas • Lalawigan ng Isabela • Tanggapan ng Panlalawigang Tagapagtasa",
            footerAct: "Alinsunod sa Batas Republika Blg. 11032 ukol sa Ease of Doing Business",
            footerRights: "Pamahalaang Panlalawigan ng Isabela. Lahat ng Karapatan ay Nakalaan."
        },

        en: {
            pageTitle: "Client Satisfaction Measurement (CSM) — Provincial Assessor of Isabela",
            agencyTitle: "PROVINCIAL GOVERNMENT OF ISABELA",
            officeTitle: "OFFICE OF THE PROVINCIAL ASSESSOR",
            csmSub: "Client Satisfaction Measurement (CSM)",
            
            // Section 1
            sec1Title: "1. Client Demographic Profile",
            sec1Sub: "Please provide your basic transaction information",
            sec1Badge: "Part 1",
            labelDate: "Date",
            labelClientType: "Client Type",
            optClientPlaceholder: "Select Client Type...",
            optClientCitizen: "Citizen",
            optClientBusiness: "Business",
            optClientGov: "Government",
            labelGender: "Gender",
            optGenderPlaceholder: "Select Gender...",
            optGenderFemale: "Female",
            optGenderMale: "Male",
            labelAge: "Age",
            optAgePlaceholder: "Select Age...",
            optAge19: "19 and below",
            optAge20: "20-34 years old",
            optAge35: "35-49 years old",
            optAge50: "50-64 years old",
            optAge65: "65 and above",
            labelRegion: "Region",
            labelProvince: "Province",
            optProvIsabela: "Isabela",
            optProvOutside: "Outside Isabela",
            labelMuni: "Municipality / City",
            optMuniPlaceholder: "Select Municipality / City...",
            labelTrans: "Transaction Type",
            optTransPlaceholder: "Select Transaction Type...",

            // Section 2
            sec2Title: "2. Citizen's Charter (CC)",
            sec2Sub: "Knowledge and experience with the Citizen's Charter",
            sec2Badge: "Part 2",
            labelCC1: "Which of the following describes your awareness of the Citizen's Charter?",
            optCC1Placeholder: "Select answer...",
            optCC1_1: "1. I know the CC and I saw it in the office I visited.",
            optCC1_2: "2. I know the CC but I did not see it in the office.",
            optCC1_3: "3. I learned about the CC when I saw it in the office.",
            optCC1_4: "4. I do not know what the CC is and saw none.",
            labelCC2: "If aware of CC, would you say that the office's Citizen's Charter was:",
            optCC2Placeholder: "Select answer...",
            optCC2_1: "Easy to See",
            optCC2_2: "Somewhat Easy to See",
            optCC2_3: "Difficult to See",
            optCC2_4: "Not Visible at All",
            optCC2_NA: "Not Applicable",
            labelCC3: "If aware of CC, how much did it help your transaction?",
            optCC3Placeholder: "Select answer...",
            optCC3_1: "Helped Very Much",
            optCC3_2: "Somewhat Helped",
            optCC3_3: "Did Not Help",
            optCC3_NA: "Not Applicable",

            // Section 3
            sec3Title: "3. Service Quality Dimensions (SQD)",
            sec3Sub: "Please rate each dimension that reflects your experience",
            sec3Badge: "Part 3",
            ratingGuideTitle: "Rating Scale Guide:",
            legend1: "Strongly Disagree",
            legend2: "Disagree",
            legend3: "Neutral",
            legend4: "Agree",
            legend5: "Strongly Agree",
            legendNA: "Not Applicable",

            sqd: {
                sqd0: "SQD0. I am satisfied with the service that I availed.",
                sqd1: "SQD1. I spent a reasonable amount of time for my transaction.",
                sqd2: "SQD2. The office followed the transaction's requirements and steps based on information provided.",
                sqd3: "SQD3. The steps, including payment, were easy and simple.",
                sqd4: "SQD4. I easily found information about my transaction from the office or its website.",
                sqd5: "SQD5. I paid a reasonable amount for my transaction. (If free, select N/A)",
                sqd6: "SQD6. I feel that the office is fair to everyone, with no favoritism in my transaction.",
                sqd7: "SQD7. I was treated with respect by the staff, and they were eager to help me.",
                sqd8: "SQD8. I got what I needed from the government office, or if denied, it was adequately explained."
            },

            likertLabels: {
                "1": "Strongly Disagree",
                "2": "Disagree",
                "3": "Neutral",
                "4": "Agree",
                "5": "Strongly Agree",
                "6": "Not Applicable"
            },

            // Section 4
            sec4Title: "4. Suggestions and Comments",
            sec4Sub: "Share your thoughts to help us continually improve our public service",
            sec4Badge: "Part 4",
            labelSuggestions: "Suggestions on how we can further improve our service:",
            suggestionsPlaceholder: "Write your comments, observations, or suggestions to help us improve...",
            labelEmail: "Email Address:",
            emailPlaceholder: "name@email.com",
            emailNote: "Provide your email if you wish to receive a response regarding your feedback.",

            // Submit & Footer
            btnSubmitText: "Submit Survey",
            submittingText: "Submitting...",
            footerGov: "Republic of the Philippines • Province of Isabela • Office of the Provincial Assessor",
            footerAct: "In compliance with Republic Act No. 11032 on Ease of Doing Business",
            footerRights: "Provincial Government of Isabela. All rights reserved."
        },

        ilo: {
            pageTitle: "Pangrukod iti Pankapnek ti Kliyente (CSM) — Probinsial nga Asesor ti Isabela",
            agencyTitle: "GUBIERNO PROBINSIAL TI ISABELA",
            officeTitle: "OPISINA TI PROBINSIAL NGA ASESOR",
            csmSub: "Pangrukod iti Pankapnek ti Kliyente (CSM)",
            
            // Section 1
            sec1Title: "1. Impormasion ti Kliyente",
            sec1Sub: "Ikkon dagiti umno nga impormasion maipanggep ti transaksion",
            sec1Badge: "Paset 1",
            labelDate: "Petsa",
            labelClientType: "Kita ti Kliyente",
            optClientPlaceholder: "Pilion ti Kita ti Kliyente...",
            optClientCitizen: "Umili",
            optClientBusiness: "Negosio",
            optClientGov: "Empleado ti Gubierno wenno Ahensia",
            labelGender: "Kasasaad",
            optGenderPlaceholder: "Pilion ti Kasasaad...",
            optGenderFemale: "Babai",
            optGenderMale: "Lalaki",
            labelAge: "Tawen",
            optAgePlaceholder: "Pilion ti Tawen...",
            optAge19: "19 agpababa",
            optAge20: "20-34 ti tawenna",
            optAge35: "35-49 ti tawenna",
            optAge50: "50-64 ti tawenna",
            optAge65: "65 agpangato",
            labelRegion: "Rehion",
            labelProvince: "Probinsia",
            optProvIsabela: "Isabela",
            optProvOutside: "Sabali a Probinsia",
            labelMuni: "Ili / Siudad",
            optMuniPlaceholder: "Pilion ti Ili wenno Siudad...",
            labelTrans: "Kita ti Transaksion",
            optTransPlaceholder: "Pilion ti Kita ti Transaksion...",

            // Section 2
            sec2Title: "2. Citizen's Charter (CC)",
            sec2Sub: "Pakaammo ken kapadasan maipanggep iti Citizen's Charter",
            sec2Badge: "Paset 2",
            labelCC1: "Pilion ti kasayaatan a mangiladawan iti pannakaammom iti Citizen's Charter:",
            optCC1Placeholder: "Pilion ti sungbat...",
            optCC1_1: "1. Ammok ti CC ken nakitak daytoy iti opisina a binisitak.",
            optCC1_2: "2. Ammok ti CC ngem saanko a nakita iti opisina.",
            optCC1_3: "3. Naammuak ti CC idi nakitak daytoy iti opisina.",
            optCC1_4: "4. Saanko nga ammo ti CC ken awan nakitak iti opisina.",
            labelCC2: "No ammom ti CC, maibagam kadi a ti CC iti napapanam nga opisina ket:",
            optCC2Placeholder: "Pilion ti sungbat...",
            optCC2_1: "Nalaka a Makita",
            optCC2_2: "Medyo Nalaka a Makita",
            optCC2_3: "Narigat a Makita",
            optCC2_4: "Saan a Pulos Makita",
            optCC2_NA: "Saan a Maiparbeng",
            labelCC3: "No ammom ti CC, kasano a nakatulong ti CC iti transaksionmo?",
            optCC3Placeholder: "Pilion ti sungbat...",
            optCC3_1: "Nakatulong Unay",
            optCC3_2: "Nakatulong Met Laeng",
            optCC3_3: "Saan a Nakatulong",
            optCC3_NA: "Saan a Maiparbeng",

            // Section 3
            sec3Title: "3. Rukod ti Kinasayaat ti Serbisio (SQD)",
            sec3Sub: "Pilion ti kapatgan a marka para iti tunggal aspeto ti serbisio",
            sec3Badge: "Paset 3",
            ratingGuideTitle: "Giya ti Panagmarka:",
            legend1: "Saan unay a mangannugot",
            legend2: "Saan a mangannugot",
            legend3: "Neutral",
            legend4: "Mangannugot",
            legend5: "Mangannugot unay",
            legendNA: "Saan a Maiparbeng",

            sqd: {
                sqd0: "SQD0. Napnekak unay iti serbisio nga inawatko manipud iti opisina.",
                sqd1: "SQD1. Nainkalintegan ti kaatiddog ti oras a binusbosko para iti transaksionko.",
                sqd2: "SQD2. Sinurot ti opisina dagiti kasapulan a dokumento ken addang sigun iti impormasion nga intedda.",
                sqd3: "SQD3. Dagiti addang ti panagproseso, agraman ti panagbayad, ket nalaka ken simple laeng.",
                sqd4: "SQD4. Nalaka ken napartak ti panagsapulko iti impormasion maipanggep ti transaksionko manipud iti opisina wenno website.",
                sqd5: "SQD5. Nainkalintegan ti gatad a binayadak para iti transaksionko. (No libre, pilion ti N/A)",
                sqd6: "SQD6. Nariknak a patas ti opisina iti amin, awan ti 'palakasan' wenno paboritismo iti transaksionko.",
                sqd7: "SQD7. Sidadaag ken siraraem a trinatodak dagiti empleado, ken situtulokda a timmulong kaniak.",
                sqd8: "SQD8. Naawatko ti kasapulanko iti opisina, wenno no saan man a naaprobaran, naipaliwanag a naimbag ti rason."
            },

            likertLabels: {
                "1": "Saan unay a mangannugot",
                "2": "Saan a mangannugot",
                "3": "Awan dasiganna",
                "4": "Mangannugot",
                "5": "Mangannugot unay",
                "6": "Saan a Maiparbeng"
            },

            // Section 4
            sec4Title: "4. Dagiti Singasing ken Komento",
            sec4Sub: "Ibinglay dagiti kapanunotan tapno mapaas-as pay ti serbisiomi kadakayo",
            sec4Badge: "Paset 4",
            labelSuggestions: "Dagiti singasing no kasano pay a mapasayaat ti serbisiomi kadakayo:",
            suggestionsPlaceholder: "Isurat ditoy dagiti obserbasion, komento, wenno singasing tapno mapaas-as pay ti serbisiomi...",
            labelEmail: "Email Address:",
            emailPlaceholder: "nagan@email.com",
            emailNote: "Isurat ti email no kayatyo ti makaawat ti sungbat manipud kadakami maipanggep ti komentoyo.",

            // Submit & Footer
            btnSubmitText: "Isumite ti Pagsusuri",
            submittingText: "Isubsubmite...",
            footerGov: "Republika ti Filipinas • Probinsia ti Isabela • Opisina ti Probinsial nga Asesor",
            footerAct: "Sigun iti Batas Republika Blg. 11032 ukol iti Ease of Doing Business",
            footerRights: "Gubierno Probinsial ti Isabela. Amin a Karbengan ket Nailanad."
        }
    };

    function updateText(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    function updateHtml(id, html) {
        const el = document.getElementById(id);
        if (el) el.innerHTML = html;
    }

    function updatePlaceholder(id, ph) {
        const el = document.getElementById(id);
        if (el) el.setAttribute('placeholder', ph);
    }

    window.switchLanguage = function(lang) {
        if (!translations[lang]) lang = 'fil';
        const t = translations[lang];

        // 1. Update Document Metadata
        document.documentElement.lang = (lang === 'ilo') ? 'ilo' : (lang === 'en' ? 'en' : 'fil');
        document.title = t.pageTitle;

        // 2. Header
        updateText('agencyTitle', t.agencyTitle);
        updateText('officeTitle', t.officeTitle);
        updateText('csmSub', t.csmSub);

        // 3. Section 1: Demographics
        updateText('sec1Title', t.sec1Title);
        updateText('sec1Sub', t.sec1Sub);
        updateText('sec1Badge', t.sec1Badge);
        updateText('labelDate', t.labelDate);
        updateText('labelClientType', t.labelClientType);
        updateText('optClientPlaceholder', t.optClientPlaceholder);
        updateText('optClientCitizen', t.optClientCitizen);
        updateText('optClientBusiness', t.optClientBusiness);
        updateText('optClientGov', t.optClientGov);

        updateText('labelGender', t.labelGender);
        updateText('optGenderPlaceholder', t.optGenderPlaceholder);
        updateText('optGenderFemale', t.optGenderFemale);
        updateText('optGenderMale', t.optGenderMale);

        updateText('labelAge', t.labelAge);
        updateText('optAgePlaceholder', t.optAgePlaceholder);
        updateText('optAge19', t.optAge19);
        updateText('optAge20', t.optAge20);
        updateText('optAge35', t.optAge35);
        updateText('optAge50', t.optAge50);
        updateText('optAge65', t.optAge65);

        updateText('labelRegion', t.labelRegion);
        updateText('labelProvince', t.labelProvince);
        updateText('optProvIsabela', t.optProvIsabela);
        updateText('optProvOutside', t.optProvOutside);
        updateText('labelMuni', t.labelMuni);
        updateText('optMuniPlaceholder', t.optMuniPlaceholder);
        updateText('labelTrans', t.labelTrans);
        updateText('optTransPlaceholder', t.optTransPlaceholder);

        // 4. Section 2: Citizen's Charter
        updateText('sec2Title', t.sec2Title);
        updateText('sec2Sub', t.sec2Sub);
        updateText('sec2Badge', t.sec2Badge);
        updateText('labelCC1', t.labelCC1);
        updateText('optCC1Placeholder', t.optCC1Placeholder);
        updateText('optCC1_1', t.optCC1_1);
        updateText('optCC1_2', t.optCC1_2);
        updateText('optCC1_3', t.optCC1_3);
        updateText('optCC1_4', t.optCC1_4);

        updateText('labelCC2', t.labelCC2);
        updateText('optCC2Placeholder', t.optCC2Placeholder);
        updateText('optCC2_1', t.optCC2_1);
        updateText('optCC2_2', t.optCC2_2);
        updateText('optCC2_3', t.optCC2_3);
        updateText('optCC2_4', t.optCC2_4);
        updateText('optCC2_NA', t.optCC2_NA);

        updateText('labelCC3', t.labelCC3);
        updateText('optCC3Placeholder', t.optCC3Placeholder);
        updateText('optCC3_1', t.optCC3_1);
        updateText('optCC3_2', t.optCC3_2);
        updateText('optCC3_3', t.optCC3_3);
        updateText('optCC3_NA', t.optCC3_NA);

        // 5. Section 3: SQD Questions & Rating Scale Guide
        updateText('sec3Title', t.sec3Title);
        updateText('sec3Sub', t.sec3Sub);
        updateText('sec3Badge', t.sec3Badge);
        updateHtml('ratingGuideTitle', '<i class="ph-bold ph-info text-warning"></i> ' + t.ratingGuideTitle);
        updateText('legend1', t.legend1);
        updateText('legend2', t.legend2);
        updateText('legend3', t.legend3);
        updateText('legend4', t.legend4);
        updateText('legend5', t.legend5);
        updateText('legendNA', t.legendNA);

        if (t.sqd) {
            for (let i = 0; i <= 8; i++) {
                const sqdId = 'sqd' + i;
                const el = document.getElementById(sqdId + '_text');
                if (el && t.sqd[sqdId]) {
                    el.innerHTML = t.sqd[sqdId] + " <span class='text-danger'>*</span>";
                }
            }
        }

        // Likert Choices Cards Labels
        if (t.likertLabels) {
            for (let v = 1; v <= 6; v++) {
                const valStr = v.toString();
                const lText = t.likertLabels[valStr];
                if (lText) {
                    document.querySelectorAll('.likert-tile.val-' + valStr + ' .likert-label').forEach(lbl => {
                        lbl.textContent = lText;
                    });
                }
            }
        }

        // 6. Section 4: Suggestions
        updateText('sec4Title', t.sec4Title);
        updateText('sec4Sub', t.sec4Sub);
        updateText('sec4Badge', t.sec4Badge);
        updateText('labelSuggestions', t.labelSuggestions);
        updatePlaceholder('suggestions', t.suggestionsPlaceholder);
        updateText('labelEmail', t.labelEmail);
        updatePlaceholder('email', t.emailPlaceholder);
        updateHtml('emailNote', '<i class="ph-bold ph-info me-1"></i>' + t.emailNote);

        // 7. Submit button & Footer
        updateText('btnSubmitText', t.btnSubmitText);
        updateText('footerGov', t.footerGov);
        updateText('footerAct', t.footerAct);
        updateText('footerRights', '© ' + new Date().getFullYear() + ' ' + t.footerRights);

        // 8. Update Active Buttons in UI
        document.querySelectorAll('.lang-btn').forEach(btn => {
            if (btn.getAttribute('data-lang') === lang) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        // 9. Persist Choice
        localStorage.setItem(STORAGE_KEY, lang);
    };

    // Auto-init on load
    document.addEventListener('DOMContentLoaded', function() {
        const savedLang = localStorage.getItem(STORAGE_KEY) || 'fil';
        window.switchLanguage(savedLang);
    });

})();
