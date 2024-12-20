import cv2
import numpy as np

# Load YOLO files
model_config = "./yolo/yolov4.cfg"
model_weights = "./yolo/yolov4.weights"
class_names_file = "./yolo/coco.names"

# Load class names
with open(class_names_file, "r") as f:
    class_names = [line.strip() for line in f.readlines()]
print("Loaded Classes:", class_names)

# Initialize YOLO
net = cv2.dnn.readNet(model_weights, model_config)
net.setPreferableBackend(cv2.dnn.DNN_BACKEND_OPENCV)
net.setPreferableTarget(cv2.dnn.DNN_TARGET_CPU)

# Access OBS Virtual Camera
cap = cv2.VideoCapture(1)  # Use the correct index for your OBS Virtual Camera

# YOLO Parameters
conf_threshold = 0.3  # Confidence threshold
nms_threshold = 0.4   # Non-Max Suppression threshold

output_layers = net.getUnconnectedOutLayersNames()

# Main Loop
while True:
    ret, frame = cap.read()
    if not ret:
        print("Error: Unable to grab frame.")
        break

    # Prepare the frame for YOLO
    blob = cv2.dnn.blobFromImage(frame, 1 / 255.0, (416, 416), swapRB=True, crop=False)
    net.setInput(blob)
    outputs = net.forward(output_layers)

    h, w = frame.shape[:2]
    boxes = []
    confidences = []
    class_ids = []

    # Process YOLO outputs
    for output in outputs:
        for detection in output:
            scores = detection[5:]
            class_id = np.argmax(scores)
            confidence = scores[class_id]

            # Only consider "person" detections
            if confidence > conf_threshold and class_names[class_id] == "person":
                center_x, center_y, bw, bh = (
                    int(detection[0] * w),
                    int(detection[1] * h),
                    int(detection[2] * w),
                    int(detection[3] * h),
                )
                x, y = int(center_x - bw / 2), int(center_y - bh / 2)
                boxes.append([x, y, bw, bh])
                confidences.append(float(confidence))
                class_ids.append(class_id)

    # Apply Non-Max Suppression
    indices = cv2.dnn.NMSBoxes(boxes, confidences, conf_threshold, nms_threshold)

    # People counter
    people_count = 0

    if len(indices) > 0:
        for i in indices.flatten():
            x, y, bw, bh = boxes[i]
            cv2.rectangle(frame, (x, y), (x + bw, y + bh), (0, 255, 0), 2)
            cv2.putText(frame, "Person", (x, y - 10), cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 255, 0), 2)
            people_count += 1

    # Display the people count on the video
    cv2.putText(frame, f"People Count: {people_count}", (10, 30), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 255, 0), 2)

    # Show the video with detections
    cv2.imshow("YOLOv4 People Detection", frame)

    # Quit with 'q'
    if cv2.waitKey(1) & 0xFF == ord("q"):
        break

# Cleanup
cap.release()
cv2.destroyAllWindows()
