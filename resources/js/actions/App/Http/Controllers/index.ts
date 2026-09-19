import RecordingIndexController from './RecordingIndexController'
import RecorderController from './RecorderController'
import RecordingController from './RecordingController'
import RecordingUploadController from './RecordingUploadController'
import TranscriptSummaryController from './TranscriptSummaryController'
import SummaryController from './SummaryController'

const Controllers = {
    RecordingIndexController: Object.assign(RecordingIndexController, RecordingIndexController),
    RecorderController: Object.assign(RecorderController, RecorderController),
    RecordingController: Object.assign(RecordingController, RecordingController),
    RecordingUploadController: Object.assign(RecordingUploadController, RecordingUploadController),
    TranscriptSummaryController: Object.assign(TranscriptSummaryController, TranscriptSummaryController),
    SummaryController: Object.assign(SummaryController, SummaryController),
}

export default Controllers