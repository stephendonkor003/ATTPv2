<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>African Union - Think Tank Application</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body {
            font-family: 'DM Sans', sans-serif;
            background-color: #f5f5f5;
        }

        .au-header {
            background-color: #007144;
            color: #fff;
            padding: 1rem;
        }

        .step-indicator .step {
            width: 40px;
            height: 40px;
            background-color: #ccc;
            color: #fff;
            border-radius: 50%;
            text-align: center;
            line-height: 40px;
            font-weight: bold;
        }

        .step-indicator .step.active {
            background-color: #007144;
        }

        .form-stage {
            display: none;
        }

        .form-stage.active {
            display: block;
        }

        select[multiple] {
            min-height: 120px;
        }

        .read-more {
            font-size: 0.85rem;
            color: #09501b;
            text-decoration: underline;
            cursor: pointer;
            animation: blink 1.2s infinite;
            display: inline-block;
            margin-top: 4px;
        }

        @keyframes blink {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.1;
            }

            100% {
                opacity: 1;
            }
        }

        .modal-content {
            border-radius: 1rem;
            font-family: 'DM Sans', sans-serif;
            transition: all 0.3s ease-in-out;
        }

        .modal-header h5 {
            font-weight: bold;
        }

        .modal-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .custom-accordion {
            width: 100%;
            max-width: 800px;
            margin: 40px auto;
            border-radius: 8px;
            font-family: 'Segoe UI', sans-serif;
        }

        .accordion-item {
            border-bottom: 1px solid #ccc;
        }

        .accordion-header {
            background-color: #f4f4f4;
            color: #333;
            cursor: pointer;
            padding: 15px 20px;
            width: 100%;
            text-align: left;
            border: none;
            outline: none;
            font-size: 1.1rem;
            transition: background-color 0.3s ease;
        }

        .accordion-header:hover {
            background-color: #e2e2e2;
        }

        .accordion-content {
            max-height: 0;
            overflow: hidden;
            padding: 0 20px;
            background-color: #fafafa;
            transition: max-height 0.4s ease, padding 0.4s ease;
        }

        .accordion-content p {
            margin: 15px 0;
        }

        .accordion-item.active .accordion-content {
            max-height: 300px;
            padding: 15px 20px;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>

<body>

    @if (session('success'))
        <script>
            window.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Congratulations!',
                    text: 'Application sent successfully.',
                    confirmButtonColor: '#007144'
                });
            });
        </script>
    @endif

    <header class="au-header text-center">
        <h2>Call for Proposals: Strengthening African Policy Research and Regional Collaboration</h2>
    </header>

    <div class="container my-5">

        <div class="card shadow-sm p-4">
            <h3 class="mb-4">Application Form</h3>
            <p>The African Think Tank Platform (ATTP) aims to establish a sustainable platform to strengthen Africa’s
                capacity for effective policy research and evidence-based policymaking on cross-boundary priorities.
            </p>

            <p>Funded by the World Bank and implemented by the African Union Commission, the platform seeks to support
                and connect African think tanks working on critical continental issues, including economic
                transformation, climate change, trade integration, food security, human capital development, and
                digitalization, with a strong emphasis on gender and regional collaboration.
            <p>We are inviting applications from consortia of African think tanks to apply for funding under the ATTP.
                Selected consortia will contribute to the platform’s core mission by producing high-quality research,
                engaging in policy dialogue, and strengthening institutional capacity to inform continental
                policymaking.
            </p>
            {{-- <div class="custom-accordion"> --}}
            <div class="accordion-item">
                <button class="accordion-header">Section 1: Eligibility Criteria</button>
                <div class="accordion-content">
                    <p>Eligible applicants must be registered African think tanks engaged in public policy research.
                        Applications must be submitted as part of a consortium of think tanks. Each consortium must:

                        <li> Be led by a designated lead think tank.</li>
                        <li> Include representation from at least two sub-regions across the continent.</li>
                        <li> Demonstrate experience working in at least four of the six priority themes of the
                            platform.</li>
                        <li> Propose activities aligned with ATTP objectives on research, engagement, capacity
                            building, and gender representation.</li>
                    </p>
                </div>
            </div>
            <div class="accordion-item">
                <button class="accordion-header">Section 2: Selection Criteria</button>
                <div class="accordion-content">
                    <p>Applications will be reviewed in a two-stage process. First, proposals will be screened for
                        eligibility. Second, eligible proposals will be evaluated by an Independent Expert Committee
                        based on:
                        <li> The quality and relevance of the research and engagement proposal.</li>
                        <li> Capacity of personnel and institutions.</li>
                        <li> Potential for policy impact.</li>
                        <li> Financial soundness and justification of the budget.</li>
                        <li> Gender and geographic diversity.</li>

                        Final approval will be made by the Think Tank Platform Steering Committee (TTPSC).
                    </p>
                </div>
            </div>
            <div class="accordion-item">
                <button class="accordion-header">Section 3: Timeline and How to Apply:</button>
                <div class="accordion-content">
                    <p>Proposals must be submitted by<strong> September 24, 2025</strong>. Late or incomplete
                        submissions will not be considered.Interested consortia should follow the instructions on the
                        ‘How to Apply’ section below to submit
                        their application.</p>
                </div>
            </div>

            {{-- <div class="accordion-item">
                <button class="accordion-header">Section 4: How to Apply for the Africa Think Tank Platform Project Call
                    for Proposals:</button>
                <div class="accordion-content">
                    <p>
                        This page provides step-by-step guidance for think tank consortia interested in applying for
                        funding through the Africa Think Tank Platform Project (ATTP). Please read the eligibility
                        criteria carefully and follow the instructions for registration and application submission.
                    </p>
                    <p>1. Eligibility Criteria</p>
                    <p>To be eligible for funding, consortia must meet the following requirements:</p>
                    <ul>
                        <li> Consortium Structure: Applications must be submitted by a consortium of 3 to 5 African
                            think
                            tanks, with one designated as the lead applicant.</li>

                        <li> Geographic Registration: All participating think tanks must be legally registered and
                            based in
                            continental Africa.</li>

                        <li> Policy Coverage: The consortium must demonstrate prior or ongoing experience in at least
                            four of the six ATTP priority themes:
                        </li>

                    </ul>

                </div>
            </div>

            <div class="accordion-item">
                <button class="accordion-header">Eligibility Criteria</button>
                <div class="accordion-content">
                    <p>
                        To be eligible for funding, consortia must meet the following requirements:
                    </p>
                    <p>1. Eligibility Criteria</p>
                    <p>
                        <li>Consortium Structure: Applications must be submitted by a consortium of 3 to 5 African think
                            tanks, with one designated as the lead applicant.
                        <li>Geographic Registration: All participating think tanks must be legally registered and based
                            in
                            continental Africa.
                        <li> Policy Coverage: The consortium must demonstrate prior or ongoing experience in at least
                            four
                            of the six ATTP priority themes:
                            <p>1. Economic transformation and governance</p>
                            <p>2. Climate change</p>
                            <p>3. Regional trade</p>
                        </li>
                    </p>


                </div>
            </div> --}}
            <p>
            <h2>How to Apply for the Africa Think Tank Platform Project Call for Proposals</h2>
            <p>This page provides step-by-step guidance for think tank consortia interested in applying for funding
                through the Africa Think Tank Platform Project (ATTP). Please read the eligibility criteria carefully
                and follow the instructions for registration and application submission.
            </p>

            <p>
            <h3>1. Eligibility Criteria</h3>

            <p>To be eligible for funding, consortia must meet the following requirements:</p>

            <p> Consortium Structure: Applications must be submitted by a consortium of 3 to 5 African think tanks,
                with one designated as the lead applicant.</p>
            <li>Geographic Registration: All participating think tanks must be legally registered and based in
                continental Africa.</li>
            <li> Policy Coverage: The consortium must demonstrate prior or ongoing experience in at least four of the
                six ATTP priority themes:</li>
            <li>1. Economic transformation and governance</li>
            <li>2. Climate change</li>
            <li>3. Regional trade</li>
            <li> 4. Food security</li>
            <li> 5. Human capital</li>
            <li> 6. Digitalization</li>
            </p>

            <p>Strategic Alignment: Proposed activities must directly contribute to the ATTP's objectives of
                generating high-quality research, supporting regional policy development, promoting institutional
                capacity building, and increasing the representation of women in policy research.</p>

            <h3>2. Application Documents</h3>

            <p>Each registered consortium must submit a complete application package comprising the documents listed
                below. All documents must be submitted in PDF format via the secure [ATTP Application Upload Portal –
                Insert Link].</p>

            <strong>Required Documents:</strong><br>

            <p><strong>A. Application Form</strong>
            <p>

                The official form, which includes detailed instructions on how to complete the form, captures consortium
                information, research topics, proposed activities, capacity-building plans, and gender strategies.
                🔗 [Download Application Form (.docx)] <br>
                </br>
            <p> </p>

            <p><strong>B. Work Plan and Budget</strong></p>

            A detailed activity timeline (Gantt chart) and financial plan using the standard template. Annual
            allocations must not exceed 30% of each think tank’s operating budget.

            🔗 [Download Budget and Timeline Template (.xlsx)] <br>
            </br>
            <p> </p>

            <p><strong>C. Past Research and Engagement Experience</strong></p>

            Use the template provided to summarize key past research or policy engagement activities relevant to
            ATTP
            themes.

            🔗 [Download Past Research Experience Template (.docx)]<br>
            </br>
            <p> </p>

            <p><strong>D. Audited Financial Reports</strong></p>

            The most recent audited accounts for each consortium member to demonstrate financial accountability and
            grant-readiness.

            E. CVs of Key Personnel
            o Consortium Coordinator
            o Deputy Coordinator
            o Two lead researchers per think tank
            Use the standard CV format.

            🔗 [Download CV Template (.docx)] <br>
            </br>
            <p> </p>

            <p><strong> E. Consortium Commitment Letter</strong></p>

            Signed by all participating think tanks, confirming shared ownership of the proposal, willingness to
            collaborate, and commitment to project implementation.

            🔗 [Download Commitment Letter Template (.docx)]

            Applicants will receive an automatic email confirming successful submission.
            <p>

            <h3>3. Register Your Consortium and Submit the Consortium’s Proposal</h3>

            <p>Before submitting an application, all consortia must register online. Registration is required to:</p>

            <li>Create a consortium profile</li>
            <li> a lead applicant Submit the consortium proposal</li>
            <li>Receive access to the secure document portal to review the consortium submission</li>

            <li>🔗 <a href="https://africathinktankplatform.africa/callforproposal">Register and submit the consortium’s
                    proposal
                    here</a></li>


            <p> Once registered, applicants will receive an email with login credentials to download the call for
                proposal documents and upload instructions.</p>
            </p>



            <p>
            <h3>4. Submission Deadline</h3>

            <p>All application documents must be submitted no later than:</p>

            <li>September 24, 2025, at 23:59 East Africa Time (EAT) Late or incomplete applications will not be
                considered.</li>

            <br>

            <h3>5. Support for Applicants To assist consortia in preparing strong proposals, the AUC offers the
                following resources:</h3>
            <br>


            <p> Contact the Secretariat: <strong>attpinfo@africanunion.org </strong></p>

            <li><strong> Informational Webinars:</strong>

                Join live sessions to learn more about ATTP objectives, proposal preparation, budgeting, and common
                application pitfalls.
            </li>

            <br>
            <li>Webinar Schedule:<br>

                <br>
                <ul>
                    <strong> July 24, 2025: Launch of ATTP Call for Proposals</strong> <br>
                    <strong> August 5, 2025: Follow-up Webinar</strong><br>
                    <strong> August 26, 2025: Follow-up Webinar</strong><br>
                    <strong> September 9, 2025: Follow-up Webinar</strong><br>
                    <strong> September 23, 2025: Follow-up Webinar </strong>
                </ul>
            </li>
            </p>

            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>Whoops!</strong> There were some problems with your submission:<br><br>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif



            @if ($callForProposalSubmissionsOpen ?? false)
            <div class="step-indicator mb-4 d-flex justify-content-center gap-3">
                <div class="step step-1 active"> 1</div>
                <div class="step step-3"> 2</div>
            </div>

            <form id="thinkTankForm" action="{{ route('applicants.store') }}" method="POST"
                enctype="multipart/form-data">
                @csrf


                <br>
                <h3>REGISTER AND SUBMIT THE CONSORTIUM’S PROPOSAL</h3>
                <!-- Stage 1 -->
                <div class="form-stage stage-1 active">
                    <h5>Stage 1: Think Tank Information</h5>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <label class="form-label">Lead Think Tank Name</label>
                            <span class="read-more" data-bs-toggle="modal" data-bs-target="#tipThinkTankName">Read
                                more</span>
                            <select name="think_tank_name" id="thinkTankName" class="form-select"
                                onchange="toggleCustomThinkTank(this)" required>
                                <option value="">-- Select Think Tank --</option>
                                @foreach ($thinkTanks as $tank)
                                    <option value="{{ $tank }}">{{ $tank }}</option>
                                @endforeach
                                <option value="Other">Other (Type below)</option>
                            </select>

                            <input type="text" name="custom_think_tank" id="customThinkTankInput"
                                class="form-control mt-2 d-none" placeholder="Enter your Think Tank name" />
                        </div>

                        <div class="modal fade" id="tipThinkTankName" tabindex="-1"
                            aria-labelledby="tipThinkTankNameLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content shadow border-0 rounded-4 overflow-hidden">
                                    <div class="modal-header" style="background-color: #007144; color: #fff;">
                                        <h5 class="modal-title" id="tipThinkTankNameLabel">Guidance: Think Tank Name
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body" style="padding: 1.5rem;">
                                        Please enter the official and full name of the lead Think Tank leading the
                                        consortium. This should match the name used in registration documents, reports,
                                        or any public communications. Avoid abbreviations unless officially recognized.

                                        Need further help? Contact ATTP Secretariat.

                                    </div>
                                    <div class="modal-footer" style="background-color: #007144; color: #fff;">
                                        <small class="me-auto">Need further help? Contact AU Secretariat.</small>
                                        <button type="button" class="btn btn-light btn-sm"
                                            data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>





                        <div class="col-md-4">
                            <label class="form-label">Country Location of Lead Think Tank</label>
                            <span class="read-more" data-bs-toggle="modal" data-bs-target="#tipCountry">Read
                                more</span>
                            <select name="country" class="form-select select2">
                                <option value="">-- Select Country --</option>
                                @foreach (['Algeria', 'Angola', 'Benin', 'Botswana', 'Burkina Faso', 'Burundi', 'Cabo Verde', 'Cameroon', 'Central African Republic', 'Chad', 'Comoros', 'Congo (Brazzaville)', 'Congo (Kinshasa)', "Côte d'Ivoire", 'Djibouti', 'Egypt', 'Equatorial Guinea', 'Eritrea', 'Eswatini', 'Ethiopia', 'Gabon', 'Gambia', 'Ghana', 'Guinea', 'Guinea-Bissau', 'Kenya', 'Lesotho', 'Liberia', 'Libya', 'Madagascar', 'Malawi', 'Mali', 'Mauritania', 'Mauritius', 'Morocco', 'Mozambique', 'Namibia', 'Niger', 'Nigeria', 'Rwanda', 'Sao Tome and Principe', 'Senegal', 'Seychelles', 'Sierra Leone', 'Somalia', 'South Africa', 'South Sudan', 'Sudan', 'Tanzania', 'Togo', 'Tunisia', 'Uganda', 'Zambia', 'Zimbabwe'] as $country)
                                    <option value="{{ $country }}"
                                        {{ request('country') == $country ? 'selected' : '' }}>
                                        {{ $country }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <!-- Country Tip Modal -->
                        <div class="modal fade" id="tipCountry" tabindex="-1" aria-labelledby="tipCountryLabel"
                            aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content shadow border-0 rounded-4 overflow-hidden">
                                    <div class="modal-header" style="background-color: #007144; color: #fff;">
                                        <h5 class="modal-title" id="tipCountryLabel">Guidance: Country Location of
                                            Lead Think Tank</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body" style="padding: 1.5rem;">
                                        Select the country where the lead Think Tank is officially based. This should be
                                        the primary location of registration or main operations in Africa.

                                        For cross-border Think Tanks, choose the country
                                    </div>
                                    <div class="modal-footer" style="background-color: #007144; color: #fff;">

                                        <button type="button" class="btn btn-light btn-sm"
                                            data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Consortium Name</label>
                            <span class="read-more" data-bs-toggle="modal" data-bs-target="#tipConsortiumMember">Read
                                more</span>
                            <input type="text" name="consortium_name" class="form-control" required>
                        </div>


                        <div class="col-md-4">
                            <label class="form-label">Consortium Members (separated by commas)</label>
                            <span class="read-more" data-bs-toggle="modal" data-bs-target="#tipConsortiumName">Read
                                more</span>
                            <textarea name="members_names" class="form-control" rows="1"
                                placeholder="e.g. John Doe, Jane Smith, Ahmed Musa" required></textarea>
                        </div>



                        <!-- Consortium Member Tip Modal -->
                        <div class="modal fade" id="tipConsortiumName" tabindex="-1"
                            aria-labelledby="tipConsortiumNameLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content shadow border-0 rounded-4 overflow-hidden">
                                    <div class="modal-header" style="background-color: #007144; color: #fff;">
                                        <h5 class="modal-title" id="tipConsortiumNameLabel">Guidance: Consortium
                                            Members
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white"
                                            data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body" style="padding: 1.5rem;">
                                        List all the Think Tank members who will be part of this consortium. Separate
                                        each Think Tank with a comma. Each member's name should match the name used in
                                        registration documents, reports, or any public communications. Avoid
                                        abbreviations unless officially recognized.

                                        Need further help? Contact ATTP Secretariat.

                                    </div>
                                    <div class="modal-footer" style="background-color: #007144; color: #fff;">

                                        <button type="button" class="btn btn-light btn-sm"
                                            data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Consortium Name Tip Modal -->
                        <div class="modal fade" id="tipConsortiumMember" tabindex="-1"
                            aria-labelledby="tipConsortiumNameLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content shadow border-0 rounded-4 overflow-hidden">
                                    <div class="modal-header" style="background-color: #007144; color: #fff;">
                                        <h5 class="modal-title" id="tipConsortiumNameLabel">Guidance: Consortium
                                            Name
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white"
                                            data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body" style="padding: 1.5rem;">
                                        Enter the official name of the consortium under which your Think Tank is
                                        collaborating with other organizations. This name should reflect the formal
                                        agreement or project identity involving multiple institutions.

                                    </div>
                                    <div class="modal-footer" style="background-color: #007144; color: #fff;">

                                        <button type="button" class="btn btn-light btn-sm"
                                            data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>




                        <div class="col-md-4">
                            <label class="form-label">Sub-Region (select from below)</label><br>
                            <span class="read-more" data-bs-toggle="modal" data-bs-target="#tipSubRegion">Read
                                more</span>
                            <select name="sub_region[]" class="form-select select2" multiple>
                                @foreach (['West Africa', 'East Africa', 'Central Africa', 'North Africa', 'Southern Africa'] as $region)
                                    <option value="{{ $region }}"
                                        {{ collect(request('sub_region'))->contains($region) ? 'selected' : '' }}>
                                        {{ $region }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <!-- Sub-Region Tip Modal -->
                        <div class="modal fade" id="tipSubRegion" tabindex="-1" aria-labelledby="tipSubRegionLabel"
                            aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content shadow border-0 rounded-4 overflow-hidden">
                                    <div class="modal-header" style="background-color: #007144; color: #fff;">
                                        <h5 class="modal-title" id="tipSubRegionLabel">Guidance: Sub-Region</h5>
                                        <button type="button" class="btn-close btn-close-white"
                                            data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body" style="padding: 1.5rem;">
                                        Select all African sub-regions where your Think Tank is active or has
                                        influence.
                                        You can choose more than one sub-region based on geographical operations or
                                        target areas. Hold <kbd>Ctrl</kbd> (Windows) or <kbd>Cmd</kbd> (Mac) to
                                        select
                                        multiple options.
                                    </div>
                                    <div class="modal-footer" style="background-color: #007144; color: #fff;">
                                        <small class="me-auto">Sub-regions follow AU-recognized geographical
                                            zones.</small>
                                        <button type="button" class="btn btn-light btn-sm"
                                            data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>



                        {{-- <div class="col-md-4">
                            <label class="form-label">Focus Areas</label><br>
                            <span class="read-more" data-bs-toggle="modal" data-bs-target="#tipFocusAreas">Read
                                more</span>
                            <input type="text" name="focus_areas" class="form-control">
                        </div> --}}

                        <div class="col-md-4">
                            <label class="form-label">Focus Area (select at least 4)</label><br>
                            <span class="read-more" data-bs-toggle="modal" data-bs-target="#tipFocusAreas">Read
                                more</span>
                            <select id="focusAreasSelect" name="focus_areas[]" class="form-select select2" multiple
                                required>
                                @foreach (['Economic Transformation and Governance', 'Climate Change', 'Regional Trade', 'Food Security', 'Human Capital Development', 'Digitalization'] as $area)
                                    <option value="{{ $area }}"
                                        {{ collect(old('focus_areas', request('focus_areas', [])))->contains($area) ? 'selected' : '' }}>
                                        {{ $area }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-danger d-none" id="focusAreaError">Please select at least 4 focus
                                areas.</small>
                        </div>



                        <!-- Focus Areas Tip Modal -->
                        <div class="modal fade" id="tipFocusAreas" tabindex="-1"
                            aria-labelledby="tipFocusAreasLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content shadow border-0 rounded-4 overflow-hidden">
                                    <div class="modal-header" style="background-color: #007144; color: #fff;">
                                        <h5 class="modal-title" id="tipFocusAreasLabel">Guidance: Focus Areas</h5>
                                        <button type="button" class="btn-close btn-close-white"
                                            data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body" style="padding: 1.5rem;">
                                        Select the main thematic areas to which your Think Tank is applying. The
                                        thematic areas selected should align with those listed in the application form.

                                        The selected thematic areas should cover at least four of the six priority
                                        themes.

                                    </div>
                                    <div class="modal-footer" style="background-color: #007144; color: #fff;">

                                        <button type="button" class="btn btn-light btn-sm"
                                            data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>


                        {{-- <div class="col-md-4">
                            <label class="form-label">Is this a Partnership?</label>
                            <span class="read-more" data-bs-toggle="modal" data-bs-target="#tipIsPartnership">Read
                                more</span>
                            <select name="is_partnership" class="form-select select2">
                                <option value="">Select</option>
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div> --}}
                        <!-- Is Partnership Tip Modal -->
                        {{-- <div class="modal fade" id="tipIsPartnership" tabindex="-1"
                            aria-labelledby="tipIsPartnershipLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content shadow border-0 rounded-4 overflow-hidden">
                                    <div class="modal-header" style="background-color: #007144; color: #fff;">
                                        <h5 class="modal-title" id="tipIsPartnershipLabel">Guidance: Is this a
                                            Partnership?</h5>
                                        <button type="button" class="btn-close btn-close-white"
                                            data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body" style="padding: 1.5rem;">
                                        Select “<strong>Yes</strong>” if your Think Tank operates in collaboration
                                        with
                                        one or more other organizations under a formal partnership arrangement.
                                        <br><br>
                                        Select “<strong>No</strong>” if it is a stand-alone or independent entity
                                        without formalized external partnerships.
                                    </div>
                                    <div class="modal-footer" style="background-color: #007144; color: #fff;">
                                        <small class="me-auto">Partnerships can include MOUs, joint programs, or
                                            co-branded initiatives.</small>
                                        <button type="button" class="btn btn-light btn-sm"
                                            data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div> --}}


                        <div class="col-md-4">
                            <label class="form-label">Official Email used by the Lead Think Tank or Consortium</label>
                            <span class="read-more" data-bs-toggle="modal" data-bs-target="#tipOfficialEmail">Read
                                more</span>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}"
                                required>
                            @error('email')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        <!-- Official Email Tip Modal -->
                        <div class="modal fade" id="tipOfficialEmail" tabindex="-1"
                            aria-labelledby="tipOfficialEmailLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content shadow border-0 rounded-4 overflow-hidden">
                                    <div class="modal-header" style="background-color: #007144; color: #fff;">
                                        <h5 class="modal-title" id="tipOfficialEmailLabel">Guidance: Official
                                            Email
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white"
                                            data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body" style="padding: 1.5rem;">
                                        Provide a valid <strong>official email address</strong> associated with your
                                        Think Tank or organization. This email will be used for all official
                                        communications regarding your application and must be regularly monitored.
                                        <br><br>
                                        Avoid personal emails (like Gmail/Yahoo) unless they are the official
                                        contact
                                        for your organization.
                                    </div>
                                    <div class="modal-footer" style="background-color: #007144; color: #fff;">
                                        <small class="me-auto">Ensure this email is correct, as it will receive
                                            confirmations and updates.</small>
                                        <button type="button" class="btn btn-light btn-sm"
                                            data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- <div class="col-md-4">
                            <label class="form-label">Lead Think Tank</label>
                            <span class="read-more" data-bs-toggle="modal" data-bs-target="#tipLeadThinkTank">Read
                                more</span>
                            <input type="text" name="lead_think_tank_name" class="form-control" required>
                        </div>
                        <!-- Lead Think Tank Tip Modal -->
                        <div class="modal fade" id="tipLeadThinkTank" tabindex="-1"
                            aria-labelledby="tipLeadThinkTankLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content shadow border-0 rounded-4 overflow-hidden">
                                    <div class="modal-header" style="background-color: #007144; color: #fff;">
                                        <h5 class="modal-title" id="tipLeadThinkTankLabel">Guidance: Lead Think
                                            Tank
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white"
                                            data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body" style="padding: 1.5rem;">
                                        Specify the <strong>main organization</strong> coordinating the consortium.
                                        This
                                        should be the Think Tank taking the lead in managing activities,
                                        communication,
                                        and reporting on behalf of the consortium.
                                        <br><br>
                                        Ensure the name is consistent with registration or organizational
                                        documentation.
                                    </div>
                                    <div class="modal-footer" style="background-color: #007144; color: #fff;">
                                        <small class="me-auto">This entity will serve as the focal point for AU
                                            correspondence and accountability.</small>
                                        <button type="button" class="btn btn-light btn-sm"
                                            data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div> --}}

                        {{-- <div class="col-md-4">
                            <label class="form-label">Lead Country</label>
                            <span class="read-more" data-bs-toggle="modal" data-bs-target="#tipLeadCountry">Read
                                more</span>
                            <select name="lead_think_tank_country" class="form-select select2">
                                <option value="">-- Select Lead Country --</option>
                                @foreach (['Algeria', 'Angola', 'Benin', 'Botswana', 'Burkina Faso', 'Burundi', 'Cabo Verde', 'Cameroon', 'Central African Republic', 'Chad', 'Comoros', 'Congo (Brazzaville)', 'Congo (Kinshasa)', "Côte d'Ivoire", 'Djibouti', 'Egypt', 'Equatorial Guinea', 'Eritrea', 'Eswatini', 'Ethiopia', 'Gabon', 'Gambia', 'Ghana', 'Guinea', 'Guinea-Bissau', 'Kenya', 'Lesotho', 'Liberia', 'Libya', 'Madagascar', 'Malawi', 'Mali', 'Mauritania', 'Mauritius', 'Morocco', 'Mozambique', 'Namibia', 'Niger', 'Nigeria', 'Rwanda', 'Sao Tome and Principe', 'Senegal', 'Seychelles', 'Sierra Leone', 'Somalia', 'South Africa', 'South Sudan', 'Sudan', 'Tanzania', 'Togo', 'Tunisia', 'Uganda', 'Zambia', 'Zimbabwe'] as $country)
                                    <option value="{{ $country }}"
                                        {{ old('lead_think_tank_country') == $country ? 'selected' : '' }}>
                                        {{ $country }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <!-- Lead Country Tip Modal -->
                        <div class="modal fade" id="tipLeadCountry" tabindex="-1"
                            aria-labelledby="tipLeadCountryLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content shadow border-0 rounded-4 overflow-hidden">
                                    <div class="modal-header" style="background-color: #007144; color: #fff;">
                                        <h5 class="modal-title" id="tipLeadCountryLabel">Guidance: Lead Country
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white"
                                            data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body" style="padding: 1.5rem;">
                                        Select the African country where the <strong>lead Think Tank is officially
                                            registered or primarily based</strong>. This country will be used as the
                                        main point of geographic reference for the consortium.
                                        <br><br>
                                        If the Think Tank is operational in multiple countries, choose the one with
                                        the
                                        most formal authority or legal registration.
                                    </div>
                                    <div class="modal-footer" style="background-color: #007144; color: #fff;">
                                        <small class="me-auto">Only African Union member states are listed in this
                                            dropdown.</small>
                                        <button type="button" class="btn btn-light btn-sm"
                                            data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div> --}}


                        <div class="col-md-4">
                            <label class="form-label">Region</label>
                            <span class="read-more" data-bs-toggle="modal" data-bs-target="#tipRegion">Read
                                more</span>
                            <select name="consortium_region" class="form-select select2">
                                <option value="">-- Select Region --</option>
                                <option>West Africa</option>
                                <option>East Africa</option>
                                <option>Central Africa</option>
                                <option>Southern Africa</option>
                                <option>North Africa</option>
                            </select>
                        </div>
                        <!-- Region Tip Modal -->
                        <div class="modal fade" id="tipRegion" tabindex="-1" aria-labelledby="tipRegionLabel"
                            aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content shadow border-0 rounded-4 overflow-hidden">
                                    <div class="modal-header" style="background-color: #007144; color: #fff;">
                                        <h5 class="modal-title" id="tipRegionLabel">Guidance: Region</h5>
                                        <button type="button" class="btn-close btn-close-white"
                                            data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body" style="padding: 1.5rem;">
                                        Choose the <strong>African region</strong> in which the consortium primarily
                                        operates or is registered.
                                        <br><br>
                                        The five recognized AU regions are: West Africa, East Africa, Central
                                        Africa,
                                        Southern Africa, and North Africa. Select the one that best represents your
                                        geographic base or coordination area.
                                    </div>
                                    <div class="modal-footer" style="background-color: #007144; color: #fff;">
                                        <small class="me-auto">Your selected region will help align your consortium
                                            within AU regional frameworks.</small>
                                        <button type="button" class="btn btn-light btn-sm"
                                            data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Covered Countries</label>
                            <span class="read-more" data-bs-toggle="modal" data-bs-target="#tipCoveredCountries">Read
                                more</span>
                            <select name="covered_countries[]" class="form-select select2" multiple>
                                @foreach (['Algeria', 'Angola', 'Benin', 'Botswana', 'Burkina Faso', 'Burundi', 'Cabo Verde', 'Cameroon', 'Central African Republic', 'Chad', 'Comoros', 'Congo (Brazzaville)', 'Congo (Kinshasa)', "Côte d'Ivoire", 'Djibouti', 'Egypt', 'Equatorial Guinea', 'Eritrea', 'Eswatini', 'Ethiopia', 'Gabon', 'Gambia', 'Ghana', 'Guinea', 'Guinea-Bissau', 'Kenya', 'Lesotho', 'Liberia', 'Libya', 'Madagascar', 'Malawi', 'Mali', 'Mauritania', 'Mauritius', 'Morocco', 'Mozambique', 'Namibia', 'Niger', 'Nigeria', 'Rwanda', 'Sao Tome and Principe', 'Senegal', 'Seychelles', 'Sierra Leone', 'Somalia', 'South Africa', 'South Sudan', 'Sudan', 'Tanzania', 'Togo', 'Tunisia', 'Uganda', 'Zambia', 'Zimbabwe'] as $country)
                                    <option value="{{ $country }}"
                                        {{ collect(old('covered_countries'))->contains($country) ? 'selected' : '' }}>
                                        {{ $country }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <!-- Covered Countries Tip Modal -->
                        <div class="modal fade" id="tipCoveredCountries" tabindex="-1"
                            aria-labelledby="tipCoveredCountriesLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content shadow border-0 rounded-4 overflow-hidden">
                                    <div class="modal-header" style="background-color: #007144; color: #fff;">
                                        <h5 class="modal-title" id="tipCoveredCountriesLabel">Guidance: Covered
                                            Countries</h5>
                                        <button type="button" class="btn-close btn-close-white"
                                            data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body" style="padding: 1.5rem;">
                                        Select all African countries where your Think Tank or consortium operates,
                                        delivers programs, conducts research, or has significant influence.
                                        <br><br>
                                        You may select multiple countries. Use <kbd>Ctrl</kbd> (Windows) or
                                        <kbd>Cmd</kbd> (Mac) to select more than one.
                                    </div>
                                    <div class="modal-footer" style="background-color: #007144; color: #fff;">
                                        <small class="me-auto">These countries will define your geographic scope
                                            and
                                            impact footprint.</small>
                                        <button type="button" class="btn btn-light btn-sm"
                                            data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>


                    </div>
                    <div class="text-center mt-4">
                        <button type="button" class="btn btn-success" onclick="goToStage(3)">Next</button>
                    </div>
                </div>

                <!-- Stage 2 -->


                <!-- Stage 3 -->
                <div class="form-stage stage-3">
                    <h5>Stage 3: Upload Required Files</h5>
                    <div class="row g-4">
                        @foreach ([['name' => 'application_form', 'label' => 'Application Form'], ['name' => 'legal_registration', 'label' => 'Legal Registration'], ['name' => 'trustees_formation', 'label' => 'Trustees Formation'], ['name' => 'audited_reports', 'label' => 'Audited Reports'], ['name' => 'commitment_letter', 'label' => 'Commitment Letter'], ['name' => 'work_plan_budget', 'label' => 'Work Plan & Budget'], ['name' => 'cv_coordinator', 'label' => 'CV of Coordinator'], ['name' => 'cv_deputy', 'label' => 'CV of Deputy Coordinator'], ['name' => 'cv_team_members', 'label' => 'CVs of Team Members'], ['name' => 'past_research', 'label' => 'Past Research Experience']] as $field)
                            <div class="col-md-{{ $field['name'] === 'past_research' ? '6' : '4' }}">
                                <label class="form-label">{{ $field['label'] }}</label>
                                <span class="read-more" data-bs-toggle="modal"
                                    data-bs-target="#tip_{{ $field['name'] }}">Read more</span>
                                <input type="file" name="{{ $field['name'] }}" class="form-control" required>
                            </div>
                        @endforeach
                    </div>
                    <!-- File Upload Tip Modals -->
                    @foreach ([
        ['id' => 'application_form', 'title' => 'Application Form', 'text' => 'Upload the completed application form. Accepted format: PDF'],
        ['id' => 'legal_registration', 'title' => 'Legal Registration', 'text' => 'Combine and upload copies of the legal registration certificates for each Think Tank participating in the consortium. Accepted format: PDF'],
        ['id' => 'trustees_formation', 'title' => 'Trustees Formation', 'text' => 'Combine and upload copies of official documentation confirming the formation or structure of each member of the Consortium Board of Trustees. Accepted format: PDF'],
        ['id' => 'audited_reports', 'title' => 'Audited Reports', 'text' => 'Combine and submit the latest financial audit reports (covering the past three years) for each consortium member to demonstrate transparency and accountability. Accepted format: PDF'],
        ['id' => 'commitment_letter', 'title' => 'Commitment Letter', 'text' => 'Upload a signed letter of commitment indicating your consortium’s commitment to the proposed initiative. Accepted format: PDF'],
        ['id' => 'work_plan_budget', 'title' => 'Work Plan & Budget', 'text' => 'Upload a detailed plan outlining timelines, activities, and budget allocations. Accepted format: PDF'],
        ['id' => 'cv_coordinator', 'title' => 'CV of Lead Coordinator', 'text' => 'Attach the detailed CV of the lead coordinator, highlighting leadership and project experience. Accepted format: PDF'],
        ['id' => 'cv_deputy', 'title' => 'CV of Deputy Coordinator', 'text' => 'Upload the CV of the deputy coordinator who will support project delivery. Accepted format: PDF'],
        ['id' => 'cv_team_members', 'title' => 'CVs of Team Members', 'text' => 'Combine and upload CVs of key team members involved in the project execution from each participating Think Tank. Accepted format: PDF'],
        ['id' => 'past_research', 'title' => 'Past Research Experience', 'text' => 'Attach evidence of past research work conducted by your team or organization. Accepted format: PDF'],
    ] as $item)
                        <div class="modal fade" id="tip_{{ $item['id'] }}" tabindex="-1"
                            aria-labelledby="tipLabel_{{ $item['id'] }}" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content shadow border-0 rounded-4 overflow-hidden">
                                    <div class="modal-header" style="background-color: #007144; color: #fff;">
                                        <h5 class="modal-title" id="tipLabel_{{ $item['id'] }}">Guidance:
                                            {{ $item['title'] }}</h5>
                                        <button type="button" class="btn-close btn-close-white"
                                            data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body" style="padding: 1.5rem;">
                                        {{ $item['text'] }}
                                    </div>
                                    <div class="modal-footer" style="background-color: #007144; color: #fff;">
                                        <small class="me-auto">Accepted format: PDF or DOC unless otherwise
                                            stated.</small>
                                        <button type="button" class="btn btn-light btn-sm"
                                            data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <div class="text-center mt-4">
                        <button type="button" class="btn btn-secondary me-2" onclick="goToStage(1)">Back</button>
                        <button type="submit" class="btn btn-success">Submit Application</button>
                    </div>
                </div>

            </form>
            @else
                <div class="alert alert-warning border-warning mb-0" role="alert">
                    <h4 class="alert-heading">Submissions Closed</h4>
                    <p class="mb-0">
                        The call for proposals is now closed. New submissions are no longer being accepted.
                    </p>
                </div>
            @endif

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>



    {{-- <script>
        function goToStage(stageNum) {
            const currentStage = $('.form-stage.active');
            let isValid = true;

            currentStage.find('input, select, textarea').each(function() {
                if ($(this).prop('required') && !$(this).val()) {
                    isValid = false;
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });

            if (isValid) {
                $('.form-stage').removeClass('active');
                $('.stage-' + stageNum).addClass('active');
                $('.step').removeClass('active');
                $('.step-' + stageNum).addClass('active');
            } else {
                alert('Please complete all required fields before continuing.');
            }
        }

        $(document).ready(function() {
            $('.select2').select2({
                width: '100%'
            });
        });
    </script> --}}
    <script>
        function goToStage(stageNum) {
            const currentStage = $('.form-stage.active');
            let isValid = true;

            // Custom check: Focus Areas selection (must select at least 5)
            if (currentStage.find('#focusAreasSelect').length) {
                const selectedOptions = $('#focusAreasSelect').val() || [];
                if (selectedOptions.length < 4) {
                    isValid = false;
                    $('#focusAreasSelect').addClass('is-invalid');
                    if (!$('#focusAreaError').length) {
                        $('#focusAreasSelect').after(
                            '<small class="text-danger" id="focusAreaError">Please select at least 5 focus areas.</small>'
                        );
                    }
                } else {
                    $('#focusAreasSelect').removeClass('is-invalid');
                    $('#focusAreaError').remove();
                }
            }

            // Check all other required fields
            currentStage.find('input, select, textarea').each(function() {
                if ($(this).prop('required') && !$(this).val()) {
                    isValid = false;
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });

            if (isValid) {
                $('.form-stage').removeClass('active');
                $('.stage-' + stageNum).addClass('active');
                $('.step').removeClass('active');
                $('.step-' + stageNum).addClass('active');
            } else {
                alert('Please complete all required fields before continuing- Make sure Your Focus Area is More than 3.');
            }
        }

        $(document).ready(function() {
            $('.select2').select2({
                width: '100%'
            });
        });
    </script>



    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2({
                width: '100%'
            });
        });
    </script>
    <script>
        function toggleCustomThinkTank(select) {
            const customInput = document.getElementById('customThinkTankInput');
            if (select.value === 'Other') {
                customInput.classList.remove('d-none');
                customInput.required = true;
            } else {
                customInput.classList.add('d-none');
                customInput.required = false;
            }
        }
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const headers = document.querySelectorAll(".accordion-header");

            headers.forEach(header => {
                header.addEventListener("click", function() {
                    const item = this.parentElement;
                    const openItem = document.querySelector(".accordion-item.active");

                    if (openItem && openItem !== item) {
                        openItem.classList.remove("active");
                    }

                    item.classList.toggle("active");
                });
            });
        });
    </script>





</body>

</html>
