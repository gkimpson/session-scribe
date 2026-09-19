import TranscriptSummaryController from './TranscriptSummaryController'
import SummaryController from './SummaryController'
import RecorderController from './RecorderController'
import RecordingController from './RecordingController'
import RecordingUploadController from './RecordingUploadController'

const Controllers = {
    TranscriptSummaryController: Object.assign(TranscriptSummaryController, TranscriptSummaryController),
    SummaryController: Object.assign(SummaryController, SummaryController),
    RecorderController: Object.assign(RecorderController, RecorderController),
    RecordingController: Object.assign(RecordingController, RecordingController),
    RecordingUploadController: Object.assign(RecordingUploadController, RecordingUploadController),
}

export default Controllers